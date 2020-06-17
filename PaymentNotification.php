<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Lang;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentNotification extends Notification{

    private $payment;

    use Queueable;

    public function __construct($payment){
      $this->payment = $payment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable){

        $data =  (new MailMessage)
            ->subject(Lang::getFromJson('Payment confirmation'))
            ->line(Lang::getFromJson('You are receiving this email because we received a payment request for your account.'));

        if($this->payment->bankPayment()){
            $data = $data->line($this->payment->getBankAccount());
        }
        return $data->line(Lang::getFromJson('Thanks for your order.'));



    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
