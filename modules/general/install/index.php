<?php

$altCfg = $ubillingConfig->getAlter();
if (!empty($altCfg['BOSORKUN_TOKEN'])) {
    if (!empty($altCfg['API_URL'])) {
        $bot = new Bosorkun($altCfg['BOSORKUN_TOKEN']);
       

        $inputs = wf_TextInput('newwebhookurl', __('Bot web-hook URL'), '', false, '30');
        $inputs .= wf_Submit(__('Install'));

        $form = wf_Form('', 'POST', $inputs, 'glamour');
        $form .= wf_delimiter();
        $form .= __('Example of valid web-hook URL') . ': https://yourhost.com/bosorkun_secret_url/' . wf_tag('br');
        $form .= __('Allowed ports is only 443, 80, 88, or 8443');

        show_window(__('Bot setup'), $form);

        if (ubRouting::checkPost('newwebhookurl')) {
            $bot->registerHook(ubRouting::post('newwebhookurl') . '/?module=bosorkun');
            show_info(__('Hook URL').' '.ubRouting::post('newwebhookurl').' installed');
        }
    } else {
        show_error(__('Empty API URL option!'));
    }
} else {
    show_error(__('Empty bot token option!'));
}

