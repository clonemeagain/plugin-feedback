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
        /**
          This is the sneaky part, we are loaded automatically by
          the config, but with no signal to fetch, and the
          damnable dispatcher so convoluted and broken, we
          have to get crafty!
          So a simple check against the URI, to see if we're involved
          To trigger simply append the url
          with &feedback=up&ticket=123
          The goal of this part is to inject a script that will make
          a form that will submit the actual vote.
         */
        if (isset($_GET['feedback']) && isset($_SESSION['_auth']['user-ticket'])) {
            $this->doShowAction();
        }
        elseif (isset($_SESSION) && isset($_GET['savefeedback']) && isset($_POST['vote'])) {
            $this->doSaveAction();
        }
        else {
            // Let's load up our addition to the ticket variables.. 
            Signal::connect('replace.variables', function($replacer, &$data) {
                $data['feedback'] = $this->getConfig()->get('template');
            });
        }
    }

    private function doShowAction() {
        $c               = $this->getConfig();
        // Send data to the script
        $data            = new \stdClass();
        // Actually, if we don't have the ticket ID in the URL, we can fetch it from 
        // the user session! 
        $data->ticket_id = filter_input(INPUT_GET, 'id');

        if (!$data->ticket_id) {
            // doh, we don't have the ticket id from the url, we might be able to fake it from the session
            // todo: Validate that is the correct path
            $data->ticket_id = $_SESSION['_auth']['user-ticket'];
        }

        $data->vote              = filter_input(INPUT_GET, 'feedback');
        $data->good              = $c->get('good-text');
        $data->bad               = $c->get('bad-text');
        $data->dialog_heading    = $c->get('dialog-heading-' . $data->vote);
        $data->send_button_text  = __('Send');
        $data->close_button_text = __('No Thanks');
        $data->url               = str_replace('feedback=' . $data->vote, 'savefeedback', $_SERVER['REQUEST_URI']);
        //  $ticket                  = Ticket::lookup(array('number' => $data->ticket_id));
        $data->suggestion        = $this->getSuggestion($data->ticket_id);

        // Need a way of indicating success/failure without breaking anything..
        // And, hopefully prevents the back button from resubmitting.
        // Let's start caching the output, then we can inject our script into it
        ob_start();

        // Can't do anything until the rest has run.. because we need
        // the core code to validate the user etc.. 
        register_shutdown_function(function ($data) {
            // Persist the user into the session.. ffs.
            global $ost;
            // Inject the data into the script:
            $data->token = $ost->getCSRFToken();
            $script      = file_get_contents(__DIR__ . '/replaceStateAndIndicateSuccess.js');
            $style       = file_get_contents(__DIR__ . '/feedback.css');
            $javascript  = str_replace("'#CONFIG#'", json_encode($data, JSON_FORCE_OBJECT), $script);
            print str_replace('</head>', '<style>' . $style . '</style><script type="text/javascript">' . $javascript . '</script></head>', ob_get_clean());
        }, $data);
    }

    private function doSaveAction() {
        // Actually save the feedback
        ob_start();
        register_shutdown_function(function() {
            ob_get_clean(); // Discard whatever error message osTicket would generate for our phony POST action
            $state = $this->saveFeedback();
            if ($state === TRUE) {
                Http::response(200, '{}', 'text/json');
            }
            else {
                $response          = new \stdClass();
                $response->message = $state;
                Http::response(400, json_encode($response), 'text/json');
            }
            exit();
        });
    }

    /**
     * Fetches the comments "placeholder" text from the field
     * @param int $ticket
     * @return boolean
     */
    private function getSuggestion($ticket_id) {
        $t = Ticket::lookup(['number' => $ticket_id]);
        if (!$t instanceof Ticket) {
            return '';
        }
        $fe = DynamicFormEntry::objects()
                ->filter([
            'object_id'   => $t->getId(),
            'object_type' => 'T'
        ]);
        foreach ($fe as $form) {
            $field = $form->getField($this->getConfig()->get('comments-field'));
            if (is_null($field)) {
                continue;
            }
            return $field->getConfiguration()['placeholder'];
        }
        return '';
    }

    private function saveFeedback() {
        $token     = filter_input(INPUT_POST, 'token');
        $comments  = filter_input(INPUT_POST, 'text');
        $vote      = filter_input(INPUT_POST, 'vote'); // up/down/meh etc
        $ticket_id = filter_input(INPUT_POST, 'ticket_id');
        if (!$ticket_id) {
            $ticket_id = $_SESSION['user-ticket'];
        }
        $this->log("Attempting to save feedback for $ticket_id of type $vote");

// Validate that the user has access to the ticket.. 
// which won't happen until AFTER the script has run.. ffs. 
        if ($ticket_id && isset($_SESSION['csrf']['token']) && $_SESSION['csrf']['token'] == $token) {
            // good
            $this->log("Token was right!");
        }
        else {
            return __('Access Denied. Possibly invalid ticket ID');
        }
        // TODO: Make configurable
        if (!in_array($vote, ['up', 'down', 'meh'])) {
            return __("Invalid feedback");
        }
        $this->log("Vote was ok: $vote");
        $ticket = Ticket::lookup(['number' => $ticket_id]);
        $config = $this->getConfig();
        if (!$config->get('feedback-field')) {
            // this is a failure.. we can't use this yet. 
            return __('Feedback Plugin Error: I haven\'t been configured yet!.');
        }
        $feedback_field = $config->get('feedback-field');
        $comments_field = $config->get('comments-field');

        $this->log("Looking for fields: feedback: $feedback_field as $vote & comments: $comments_field as $comments");

        if (!$ticket instanceof Ticket) {
            return __('Unable to find that ticket.');
        }

        $this->log("Saving feedback for ticket: " . $ticket->getSubject());
        $fe      = DynamicFormEntry::objects()
                ->filter(array('object_id' => $ticket->getId(), 'object_type' => 'T'));
        $changed = FALSE;
        foreach ($fe as $form) {
            $f_field = $form->getField($feedback_field);
            if (!is_null($f_field)) {
                $f_field->setValue($vote, true);
                $changed = TRUE;
            }
        }if ($comments) {
            foreach ($fe as $form) {
                $c_field = $form->getField($comments_field);
                if (!is_null($c_field)) {
                    $c_field->setValue($comments, true);
                    $changed = TRUE;
                }
            }
        }
        if ($changed) {
            $form->save();
            // might want to log the event.. 
            $ticket->logActivity(__('Feedback received: ' . $vote), $comments);
            return TRUE;
        }
        return FALSE;
    }

    private function log($text) {
        if (DEBUG) {
            error_log($text);
        }
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
        $ost->alertAdmin(__('Plugin: Feedback has been uninstalled'), __("You don't want to know what they think any more?"), true);

        parent::uninstall($errors);
    }

    /**
     * Plugins seem to want this.
     */
    public function getForm() {
        return array();
    }

}
