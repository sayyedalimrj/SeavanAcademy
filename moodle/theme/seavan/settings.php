<?php
defined('MOODLE_INTERNAL') || die();
if ($ADMIN->fulltree) {
    $settings = new admin_settingpage('themesettingseavan', get_string('pluginname','theme_seavan'));
    foreach (['fontregular','fontbold'] as $area) {
        $setting = new admin_setting_configstoredfile('theme_seavan/' . $area,
            get_string($area,'theme_seavan'), get_string('fontdesc','theme_seavan'),
            $area, 0, ['maxfiles'=>1, 'accepted_types'=>['.woff2','.woff']]);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $settings->add($setting);
    }
}
