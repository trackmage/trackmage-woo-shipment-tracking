<?php

namespace TrackMage\WordPress;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Repository\LogRepository;

require_once ABSPATH . '/wp-admin/includes/plugin.php';

class Logger implements LoggerInterface
{
    use LoggerTrait;

    private $hierarchy;

    private $logRepository;
    private $logLevel;

    /**
     * @param string $logLevel
     */
    public function __construct(LogRepository $logRepository, $logLevel = LogLevel::INFO)
    {
        $this->logRepository = $logRepository;
        $this->hierarchy = array_flip([
            LogLevel::DEBUG,
            LogLevel::INFO,
            LogLevel::NOTICE,
            LogLevel::WARNING,
            LogLevel::ERROR,
            LogLevel::CRITICAL,
            LogLevel::ALERT,
            LogLevel::EMERGENCY,
        ]);
        if (!isset($this->hierarchy[$logLevel])) {
            throw new InvalidArgumentException('Unknown log level: '.$logLevel);
        }
        $this->logLevel = $logLevel;
    }

    public function log($level, $message, array $context = array())
    {
        if (!isset($this->hierarchy[$level])) {
            throw new InvalidArgumentException('Unknown log level: '.$level);
        }
        $this->addToDb($level, $message, $context);
        $this->addToError($level, $message);
    }

    private function addToDb($level, $message, $context) {
        if ($this->hierarchy[$level] < $this->hierarchy[$this->logLevel]) {
            return;
        }
        try {
            $this->logRepository->insert([
                'message' => sprintf('[%s] %s', $level, $message),
                'context' => json_encode($context),
            ]);
        } catch(\Throwable $e) {}
    }

    private function addToError($level, $msg) {
        if ($this->hierarchy[$level] < $this->hierarchy[LogLevel::ERROR]) {
            return;
        }
        $loggerName = str_replace('.php', '', basename(TRACKMAGE_PLUGIN_FILE));
        error_log(sprintf('%s [ %s ] %s', $loggerName, $level, $msg));
    }
}
