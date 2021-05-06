<?php

$altCfg = $ubillingConfig->getAlter();
$bot = new Bosorkun($altCfg['BOSORKUN_TOKEN']);

$commands = array(
    '/start' => 'actionKeyboard',
    __('Log out') => 'actionLogOut',
    __('Profile') => 'actionProfile',
    __('Credit') => 'actionCredit',
    __('Pay') => 'actionOpenpayz',
);




$bot->setActions($commands);
$bot->listen();


