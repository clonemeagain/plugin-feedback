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
            'sb-1'                => new SectionBreakField([
                'label' => $__('Form Field Configuration'),
                'hint'  => $__('Read the README for help setting this up.')]),
            'feedback-field'      => new TextboxField([
                'label'         => $__('Feedback Field'),
                'default'       => 'feedback',
                'hint'          => $__('Create a Choices field in the Ticket Fields form, enter it\'s variable name here, use three options: "up, meh, down" with : between them and the local name, ie: up:Great each on a new line.'),
                'configuration' => array(
                    'html'   => FALSE,
                    'size'   => 50,
                    'length' => 256
                )]),
            'comments-field'      => new TextboxField([
                'label'         => $__('Feedback Comment Field'),
                'default'       => 'comments',
                'hint'          => $__('Create a Long Text field in the Ticket Fields form, enter it\'s variable here.'),
                'configuration' => array(
                    'html'   => FALSE,
                    'size'   => 50,
                    'length' => 256
                )]),
            'sb-2'                => new SectionBreakField([
                'label' => $__('Dialog Headings'),
                'hint'  => $__('Custom prompts responding to the link clicked.')]),
            'dialog-heading-up'   => new TextareaField(
                    [
                'label'         => $__('Dialog Heading: up'),
                'default'       => '<h3>Hey, thanks!</h3><p>We\'d really appreciate any extra feedback you might have</p>',
                'hint'          => $__('Prompt for positive feedback'),
                'configuration' => array(
                    'html'   => TRUE,
                    'size'   => 100,
                    'length' => 256,
                    'cols'   => 40,
                    'rows'   => 10,
                )]),
            'dialog-heading-meh'  => new TextareaField(
                    [
                'label'         => $__('Dialog Heading: meh'),
                'default'       => '<h3>You\'re not happy? How can we help?</h3><p>We\'d really appreciate your feedback.</p>',
                'hint'          => $__('Prompt for indifferent feedback'),
                'configuration' => array(
                    'html'   => TRUE,
                    'size'   => 100,
                    'length' => 256,
                    'cols'   => 40,
                    'rows'   => 10,
                )]),
            'dialog-heading-down' => new TextareaField(
                    [
                'label'         => $__('Dialog Heading: down'),
                'default'       => '<h3>Oh No! - We could use your help to improve, please, tell us what we can do</h3><p>We really appreciate your feedback.</p>',
                'hint'          => $__('Prompt for negative feedback'),
                'configuration' => array(
                    'html'   => TRUE,
                    'size'   => 100,
                    'length' => 256,
                    'cols'   => 40,
                    'rows'   => 10,
                )]),
            'sb-3'                => new SectionBreakField([
                'label' => $__('Final Message Configuration'),
                'hint'  => $__('What we say to them after they submit the form.')]),
            'good-text'           => new TextareaField([
                'label'         => $__('Success Message'),
                'default'       => 'Feedback received, thanks!',
                'hint'          => $__('Shown when the feedback worked, with a green successful colour.'),
                'configuration' => array(
                    'html'   => TRUE,
                    'length' => 10000
                )]),
            'bad-text'            => new TextareaField([
                'label'         => $__('Failure Message'),
                'default'       => 'There was a problem processing your feedback, please try again.',
                'hint'          => $__('Shown when the feedback failed, with a red colour.'),
                'configuration' => array(
                    'html'   => TRUE,
                    'length' => 10000
                )]),
            'sb-4'                => new SectionBreakField([
                'label' => $__('Template Configuration'),
                'hint'  => $__('This part configures the Template Variable.')]),
            'template'            => new TextareaField([
                'label'         => $__('Template Definition'),
                'default'       => '<p>How was your support experience?<br />
    <a href="%{recipient.ticket_link}&feedback=up" title="I liked the support!">
    <img src="%{url}/assets/default/images/icons/ok.png">It was good, thanks!</a>&nbsp;
    <a href="%{recipient.ticket_link}&feedback=meh" title="I feel neither">
    <img src="%{url}/assets/default/images/icons/alert.png">Indifferent</a>&nbsp;
    <a href="%{recipient.ticket_link}&feedback=down" title="Something went wrong?">
    <img src="%{url}/assets/default/images/icons/error.png">We need to talk...</a>
</p>', 'hint'          => $__('Apply osTicket pull-request 3111 then you can use the above as variable %{feedback} in your Canned Responses and Ticket Templates.'),
                'configuration' => [
                    'html'   => FALSE,
                    'size'   => 100,
                    'length' => 10000,
                    'cols'   => 80,
                    'rows'   => 10,
                ]
                    ]
            ),
        );
    }

}
