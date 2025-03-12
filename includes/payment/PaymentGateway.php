<?php
interface PaymentGateway {
    public function createPayment(array $paymentData);
    public function processPayment(string $paymentId, array $data);
    public function getPaymentStatus(string $paymentId);
}

class PaymentException extends Exception {} 