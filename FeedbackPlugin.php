<?php

/**
 * The goal of this Plugin is to receive up or downvotes etc 
 * on the end-users perspective of their support experience. 
 */
require_once (INCLUDE_DIR . 'class.plugin.php');
require_once (INCLUDE_DIR . 'class.signal.php');
require_once ('config.php');

/**
 * Implementation of the Plugin class
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
        } elseif (isset($_SESSION) && isset($_GET['savefeedback']) && isset($_POST['vote'])) {
            $this->doSaveAction();
        } else {
            // Let's load up our addition to the ticket variables.. 
            // assumes the pull request has been loaded.. but.. shit.
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
        $data->vote             = filter_input(INPUT_GET, 'feedback');
        $data->good             = $c->get('good-text');
        $data->bad              = $c->get('bad-text');
        $data->dialog_heading   = $c->get('dialog-heading-' . $data->vote);
        $data->send_button_text = __('Send');
        $data->url              = str_replace('feedback=' . $data->vote, 'savefeedback', $_SERVER['REQUEST_URI']);

        // Ok, now we need to get the field config for the choice, use the
        // display names as the labels and build radio buttons
        // because I just saw a great feedback form and want to 
        // emulate it.. it was beautiful!
        $data->options = $this->getFormOptions($data->ticket_id);

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
            } else {
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
    private function getFormOptions($ticket_id) {
        // Setup the default options:
        $options = [
            'help' => '',
            'up'   => __('Great'),
            'meh'  => __('Okay'),
            'down' => __('Not Good'),
        ];
        // Get the Ticket from the User number (not the id, the number):
        $t       = Ticket::lookup(['number' => $ticket_id]);
        if (!$t instanceof Ticket) {
            return $options;
        }
        // Get the form for the Ticket:
        $fe = DynamicFormEntry::objects()
                ->filter([
            'object_id'   => $t->getId(),
            'object_type' => 'T'
        ]);
        //$fe is an iterable QuerySet.. not an array!
        foreach ($fe as $form) {
            $field = $form->getField($this->getConfig()->get('feedback-field'));
            if ($field) {
                $options = $field->getChoices();
            }
        }
        $options ['placeholder'] = __('Message');
        return $options;
    }

    /**
     * Connects to a Ticket's form, saves the feedback
     * we received into the Configured Fields.
     * 
     * @return boolean
     */
    private function saveFeedback() {
        // Fetch what the User entered/selected:
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
        } else {
            return __('Access Denied. Possibly invalid ticket ID');
        }
        // Validate the Ticket:
        $ticket = Ticket::lookup(['number' => $ticket_id]);
        if (!$ticket instanceof Ticket) {
            return __('Unable to find that ticket.');
        }
        // Validate the Plugin Config:
        $config = $this->getConfig();
        if (!$config->get('feedback-field')) {
            // this is a failure.. we can't use this yet. 
            return __('Feedback Plugin Error: I haven\'t been configured yet!.');
        }
        $feedback_field_name = $config->get('feedback-field');
        $comment_field_name  = $config->get('comments-field');

        // Load the Ticket's form:
        $fe      = DynamicFormEntry::objects()->filter([
            'object_id'   => $ticket->getId(),
            'object_type' => 'T'
        ]);
        $changed = FALSE;
        // Find the feedback field and set the vote choice
        //$fe is an iterable QuerySet.. not an array!
        foreach ($fe as $form) {
            $field_feedback = $form->getField($feedback_field_name);
            if ($field_feedback) {
                $choices = $field_feedback->getChoices();
                if (isset($choices[$vote])) {
                    $ticket->logActivity(__('Feedback received') . ': ' . $choices[$vote], $comments);
                    $field_feedback->setValue($vote, true);
                    $changed = TRUE;
                } else {
                    return __("Invalid feedback.. ");
                }
            }
        }
        // Save the comment text
        foreach ($fe as $form) {
            $field_comment = $form->getField($comment_field_name);
            if ($field_comment) {
                $field_comment->setValue($comments, true);
                $changed = TRUE;
            }
        }

        if ($changed) {
            $form->save();
            // might want to log the event.. is it an event? Probably.. 
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
    function uninstall(&$errors) {
        global $ost;
        $ost->alertAdmin(__('Plugin: Feedback has been uninstalled'), __("You don't want to know what they think any more?"), true);
        parent::uninstall($errors);
    }

    /**
     * Plugins seem to want this.
     */
    public function getForm() {
        return [];
    }

}
