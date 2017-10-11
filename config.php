<?php

require_once 'config.php';
require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.message.php';
require_once INCLUDE_DIR . 'class.dynamic_forms.php';

class FeedbackPluginConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function ($x) {
                    return $x;
                },
                function ($x, $y, $n) {
                    return $n != 1 ? $y : $x;
                }
            );
        }
        return Plugin::translate('feedback');
    }

    function pre_save($config, &$errors) {
        return TRUE;
    }

    /**
     * Build an Admin settings page.
     *
     * {@inheritdoc}
     *
     * @see PluginConfig::getOptions()
     */
    function getOptions() {
        list ($__, $_N) = self::translate();
        // Get list of forms so admin can select Ticket Form, or extra form
        return array(
            'feedback-field'   => new TextboxField([
                'label'   => $__('Feedback Field'),
                'default' => 'feedback',
                'hint'    => $__('Create a Short text field in the Ticket Fields form, enter it\'s variable here.'),
                    ]),
            'comments-field' => new TextboxField([
                'label'   => $__('Feedback Comment Field'),
                'default' => 'comments',
                'hint'    => $__('Create a Long Text field in the Ticket Fields form, enter it\'s variable here.')
                    ]),
            'good-text'        => new TextboxField([
                'label'   => $__('Success Message'),
                'default' => 'Feedback received, thanks!',
                'hint'    => $__('Shown when the feedback worked, with a green successful colour.'),
                    ]),
            'bad-text'         => new TextboxField([
                'label'   => $__('Failure Message'),
                'default' => 'There was a problem processing your feedback, please try again.',
                'hint'    => $__('Shown when the feedback failed, with a red colour.'),
                    ]),
            'dialog-heading'   => new TextboxField(
                    [
                'label'         => $__('Dialog Heading'),
                'default'       => 'Any extra comments are welcome.',
                'hint'          => $__('What are we saying to the end user to ask for more comments?'),
                'configuration' => array(
                    'html'   => TRUE,
                    'size'   => 40,
                    'length' => 256
                )]),
            'details-label'    => new TextboxField(
                    [
                'label'   => $__('Dialog Details label'),
                'default' => $__('Details')
                    ]),
        );
    }

}
