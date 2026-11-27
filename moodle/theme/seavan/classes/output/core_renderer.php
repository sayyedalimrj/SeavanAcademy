<?php
// Seavan Academy theme, GPL v3 or later.

namespace theme_seavan\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Keep the login input stable while retaining Moodle's native form and authentication.
 */
class core_renderer extends \theme_boost\output\core_renderer {
    public function render_login(\core_auth\output\login $form) {
        $form = clone $form;
        // The standard asynchronous toggle replaces the input and can discard typed or autofilled passwords.
        $form->togglepassword = false;
        return parent::render_login($form);
    }
}
