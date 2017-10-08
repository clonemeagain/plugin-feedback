<?php

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
            $this->processFeedback(filter_input(INPUT_GET, 'id'), filter_input(INPUT_GET, 'feedback'));
        }
    }

    private function processFeedback($ticket_id, $type) {
        $dest = str_replace('feedback=' . $type, '', $_SERVER['REQUEST_URI']);
        // TODO: Make configurable
        if (!in_array($type, array('up', 'down', 'meh'))) {
            Messages::error("Invalid feedback.. ");
            Http::redirect($dest);
            return;
        }
        $config = $this->getConfig();
        if (!$config->get('ticket-form') || !$config->get('feedback-field')) {
            // this is a failure.. we can't use this yet. 
            global $ost;
            $ost->logError('Feedback plugin not configured with form information.');
        }
        $ticket = Ticket::lookup($ticket_id);

        // actually log the feedback against the ticket
        if ($ticket instanceof Ticket) {
            $fe = DynamicFormEntry::objects()
                    ->filter(array('object_id' => $object_id, 'object_type' => 'U'));
            foreach ($fe as $form) {
                print "<h1>Checking form: " . $form->getId() . "</h1>";
                if ($form->getId() == $config->get('ticket-form')) {

                    foreach ($fe->getFields() as $field) {
                        print "<h2>Checking field: " . $field->get('name') . '</h2>';
                        //Does the field match the name given? 
                        if ($field->get('name') == $config->get('feedback-field')) {
                            //Add the first match to the entry.
                            $fe->setValue($type, true);
                            break;
                        }
                    }        //Save all changes to the entry to the database.
                    $fe->getForm()->save();
                }
            }
        }

        Http::redirect($dest);
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
