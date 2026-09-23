<?php

namespace App\Contracts;

interface MailTransport
{
    public function id(): string;

    public function send(MailMessage $message): DeliveryResult;
}
