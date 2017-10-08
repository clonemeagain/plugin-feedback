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
        $forms = array();
        foreach (DynamicForm::objects() as $f) {
            $forms[$f->getId()] = $f->getTitle();
        }

        return array(
            'ticket-form' . $i => new ChoiceField(
                    [
                'label' => $__('Ticket Form'),
                'choices' => $forms,
                'default' => 1,
                'hint' => $__(
                        'Select the form where you made your feedback field')
                    ]),
            'feedback-field' => new TextboxField([
                'label' => $__('Feedback Field'),
                'default' => '',
                'hint' => $__('Enter the variable name you chose when making the field.'),
                    ]),
        );
    }

}
