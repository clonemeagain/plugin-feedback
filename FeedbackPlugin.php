<?php

ini_set('error_reporting', 1);
ini_set('display_errors', 1);

require_once (INCLUDE_DIR . 'class.format.php');
require_once (INCLUDE_DIR . 'class.plugin.php');
require_once (INCLUDE_DIR . 'class.signal.php');
require_once (INCLUDE_DIR . 'class.message.php');
require_once ('config.php');

/**
 * The goal of this Plugin is to receive up or downvotes etc 
 * on the end-users perspective of the support experience. 
 * 
 * Could be used as a trivial "poll" of each ticket etc. 
 */
class FeedbackPlugin extends Plugin {

    /**
     * Which config class to load
     *
     * @var string
     */
    var $config_class = 'FeedbackPluginConfig';

    /**
     * Set to TRUE to enable webserver logging, and extra logging.
     *
     * @var boolean
     */
    const DEBUG = TRUE;

    public function bootstrap() {

// This is the sneaky part, we are loaded automatically by
// the config, but with no signal to fetch, and the 
// damnable dispatcher so convoluted and broken, we
// have to get crafty!
// Do a simple check against the URI, see if we're involved
// I'm assuming using $feedback_url is outside the global scope
// for osTicket.. if it breaks, I'm sorry!
// to trigger this, simply append the ticket_view URL 
// with &feedback=up&ticket=123
        if (isset($_GET['id']) && $_GET['id'] && isset($_GET['feedback'])) {

            $ticket_id     = filter_input(INPUT_GET, 'id');
            $feedback_type = filter_input(INPUT_GET, 'feedback');
            $errors        = [];
            $status        = $this->processFeedback($ticket_id, $feedback_type, $errors);
            error_log("Status: $status");
//if ($status !== TRUE) {
// Do'h!
            error_log("Feedback Error!");
            error_log($errors['err'] . $status);
// do we just return now and abandon our efforts?
// }

            $c                       = $this->getConfig();
// Send data to the script
            $data                    = new \stdClass();
            $data->ticket_id         = $ticket_id;
            $data->good              = $c->get('good-text');
            $data->bad               = $c->get('bad-text');
            $data->dialog_heading    = $c->get('dialog-heading');
            $data->details_label     = $c->get('details-label');
            $data->send_button_text  = __('Send');
            $data->close_button_text = __('No Thanks');
            $data->status            = $status == TRUE;


// Need a way of indicating success/failure without breaking anything..
// And, hopefully prevents the back button from resubmitting.
// Let's start caching the output, then we can inject our script into it
            ob_start();

// Can't do anything until the rest has run.. because we need
// the core code to validate the user etc.. 
            register_shutdown_function(function ($data, $ticket_id, $feedback_type) {

                $script     = file_get_contents(__DIR__ . '/replaceStateAndIndicateSuccess.js');
                $javascript = str_replace("'#CONFIG#'", json_encode($data, JSON_FORCE_OBJECT), $script);
                print str_replace('</head>', '</head><script type="text/javascript">' . $javascript . '</script></head>', ob_get_clean());
            }, $data, $ticket_id, $feedback_type);
        }
        elseif (isset($_GET['id']) && isset($_GET['feedbackcomments']) && isset($POST['feedbacktext'])) {
            $ticket_id = filter_input(INPUT_GET, 'id');
            $comments  = filter_input(INPUT_POST, 'feedbacktext');
// ok, they had more to say!.. where are we putting it? 
            $this->saveFeedbackComments($ticket_id, $comments);
            $dest      = str_replace('feedback=details', '', $_SERVER['REQUEST_URI']);
            Http::redirect($dest);
        }
    }

    private function saveFeedbackComments($ticket_id, $comments, &$errors) {
        $ticket = Ticket::lookup($ticket_id);
        $config = $this->getConfig();
        if (!$config->get('ticket-form') || !$config->get('feedback-field')) {
// this is a failure.. we can't use this yet. 
            return __('Feedback Plugin Error: I haven\'t been configured yet!.');
        }
        $feedback_field = $config->get('comments-field');

// Get all the forms for this ticket:
// actually log the feedback against the ticket
        if ($ticket instanceof Ticket) {
// Build a form as the user would see on the Edit page.. hmm.. They'll need edit access 
// to the form elements!
            $fe = DynamicFormEntry::objects()
                    ->filter(array('object_id' => $ticket->getId(), 'object_type' => 'T'));
            foreach ($fe as $form) {
                print "<h1>Checking form: " . $form->getId() . "</h1>";
                $field = $form->getField($feedback_field);
                if (is_null($field)) {
                    continue;
                }
//Add the first match to the entry.
                $field->setValue($comments, true);
                $form->save();
                $ticket->logEvent('form', array('fields' => [
                        $feedback_field => 'Comment added']));
                return TRUE;
            }
            /*
             * Pinched from https://github.com/Micke1101/OSTicket-plugin-Emailform/blob/master/class.EmailformPlugin.php
             * Can't use it though, as it adds a form for every feedback.. not adding to the existing form.
             *
              if (($form = DynamicForm::lookup($config->get('ticket-form')))) {

              //Create a new entry of the form. ?? Why? Why not the original form?
              $f = $form->instanciate();

              //Assign the entry to the ticket.
              $f->setTicketId($ticket->getId());

              //Find the first threadentry of ticket (should be the original email).
              $body = $ticket->getThread()->getEntries()[0]->getBody()->getClean();

              //Iterate over all fields in the entry.
              foreach ($f->getFields() as $field) {

              //Does the regex designated to the field match anything in the body?
              if ($config->exists('emailform-' . $field->get('name')) && preg_match("/" . $config->get('emailform-'
              . $field->get('name')) . "/", $body, $matches)) {

              //Add the first match to the entry.
              $f->setAnswer($field->get('name'), $matches[0]);
              }
              }
              }
             */

            /* Pinched from the form user page.. *
              //Save all changes to the entry to the database.
              foreach ($forms as $f) {
              $changes += $f->getChanges();
              $f->save();
              }
              if ($changes) {
              $ticket->logEvent('feedback_provided', array('fields' => $changes));
              return TRUE;
              } */
        }
        return FALSE;
    }

    private function processFeedback($ticket_id, $type, &$errors) {

        error_log("Attempting to save feedback for $ticket_id of type $type");
        global $thisclient, $errors;

// TODO: Make configurable
        if (!in_array($type, array('up', 'down', 'meh'))) {
            return "Invalid feedback.. ";
        }
        $ticket = Ticket::lookup($ticket_id);
// Validate that the user has access to the ticket.. 
// which won't happen until AFTER the script has run.. ffs. 
//  if (!defined('OSTCLIENTINC') || !$thisclient || !$ticket->checkUserAccess($thisclient) //double check perm again!
//  || $thisclient->getId() != $ticket->getUserId()) {
// Check copied from /tickets.php in client area, with added null check
// and defined OSTCLIENTINC check.. just because.
// return __('Access Denied. Possibly invalid ticket ID');
// }

        $config = $this->getConfig();
        if (!$config->get('ticket-form') || !$config->get('feedback-field')) {
// this is a failure.. we can't use this yet. 
            return __('Feedback Plugin Error: I haven\'t been configured yet!.');
        }
        $ticket_form_id = $config->get('ticket-form');
        $feedback_field = $config->get('feedback-field');

// Get all the forms for this ticket:
//$forms   = DynamicFormEntry::forTicket($ticket->getId());
//  $changes = array();
//  foreach ($forms as $form) {
//     $form->filterFields(function($f) {
// only get fields we can save: TODO: Figure out how to 
// filter the fields we get to just the one we want!
//           return !$f->isStorable();
//       });
//   }
// actually log the feedback against the ticket
        if ($ticket instanceof Ticket) {
// Build a form as the user would see on the Edit page.. hmm.. They'll need edit access 
// to the form elements!
            $fe = DynamicFormEntry::objects()
                    ->filter(array('object_id' => $ticket->getId(), 'object_type' => 'T'));
            foreach ($fe as $form) {
                print "<h1>Checking form: " . $form->getId() . "</h1>";
                $field = $form->getField($feedback_field);
                if (is_null($field)) {
                    continue;
                }
//Add the first match to the entry.
                $field->setValue($type, true);
                $form->save();
                $ticket->logEvent('feedback_provided', array('fields' => [
                        $feedback_field => $type]));
                return TRUE;
            }
            /*
             * Pinched from https://github.com/Micke1101/OSTicket-plugin-Emailform/blob/master/class.EmailformPlugin.php
             * Can't use it though, as it adds a form for every feedback.. not adding to the existing form.
             *
              if (($form = DynamicForm::lookup($config->get('ticket-form')))) {

              //Create a new entry of the form. ?? Why? Why not the original form?
              $f = $form->instanciate();

              //Assign the entry to the ticket.
              $f->setTicketId($ticket->getId());

              //Find the first threadentry of ticket (should be the original email).
              $body = $ticket->getThread()->getEntries()[0]->getBody()->getClean();

              //Iterate over all fields in the entry.
              foreach ($f->getFields() as $field) {

              //Does the regex designated to the field match anything in the body?
              if ($config->exists('emailform-' . $field->get('name')) && preg_match("/" . $config->get('emailform-'
              . $field->get('name')) . "/", $body, $matches)) {

              //Add the first match to the entry.
              $f->setAnswer($field->get('name'), $matches[0]);
              }
              }
              }
             */

            /* Pinched from the form user page.. *
              //Save all changes to the entry to the database.
              foreach ($forms as $f) {
              $changes += $f->getChanges();
              $f->save();
              }
              if ($changes) {
              $ticket->logEvent('feedback_provided', array('fields' => $changes));
              return TRUE;
              } */
        }
        return FALSE;
    }

    /**
     * Required stub.
     *
     * {@inheritdoc}
     *
     * @see Plugin::uninstall()
     */
    function uninstall() {
        $errors = array();
// Do we send an email to the admin telling him about the space used by the archive?
        global $ost;
        $ost->alertAdmin('Plugin: Feedback has been uninstalled', "Forwarded messages will now appear from the forwarder, as with normal email.", true);

        parent::uninstall($errors);
    }

    /**
     * Plugins seem to want this.
     */
    public function getForm() {
        return array();
    }

}
