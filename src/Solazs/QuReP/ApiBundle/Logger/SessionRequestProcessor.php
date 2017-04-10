<?php
/**
 * Created by PhpStorm.
 * User: solazs
 * Date: 2017.04.10.
 * Time: 13:59
 */

namespace Solazs\QuReP\ApiBundle\Logger;

use Symfony\Component\HttpFoundation\Session\Session;

class SessionRequestProcessor
{
    private $session;
    private $token;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function processRecord(array $record)
    {
        if (null === $this->token) {
            try {
                $this->token = substr($this->session->getId(), 0, 8);
            } catch (\RuntimeException $e) {
                $this->token = '????????';
            }
            $this->token .= '-'.substr(uniqid(), -8);
        }
        $record['extra']['request_logger_token'] = $this->token;

        return $record;
    }
}