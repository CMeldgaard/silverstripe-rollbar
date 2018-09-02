<?php

namespace silverstripe\rollbar\Adaptor;

use silverstripe\rollbar\Adaptor\RollbarClientAdaptor;
use silverstripe\rollbar\Exception\RollbarLogWriterException;

/**
 * The RollbarClient class simply acts as a bridge between the Raven PHP SDK and
 * the RollbarLogWriter class itself. Any {@link RollbarClientAdaptor} subclass
 * should be able to be swapped-out and used at any point.
 */

class RollbarClient extends RollbarClientAdaptor
{

    /**
     * It's an ERROR unless proven otherwise!
     *
     * @var    string
     * @config
     */
    private static $default_error_level = 'ERROR';

    /**
     * @var Raven_Client
     */
    protected $client;

    /**
     * A mapping of log-level values between Zend_Log => Raven_Client
     *
     * @var array
     */
   // protected $logLevels = [
    //    'NOTICE'    => \Raven_Client::INFO,
   //     'WARN'      => \Raven_Client::WARNING,
   //     'ERR'       => \Raven_Client::ERROR,
   //     'EMERG'     => \Raven_Client::FATAL
   // ];

    /**
     * @throws RollbarLogWriterException
     * @return void
     */
    public function __construct()
    {
        if (!$access_token = $this->getSettings('access_token')) {
            $msg = sprintf("%s requires a access token to be set in config.", __CLASS__);
            throw new RollbarLogWriterException($msg);
        }

        //$this->client = new \Raven_Client($access_token);

        // Installs all available PHP error handlers when set
        if ($this->config()->install === true) {
            $this->client->install();
        }
    }

    /**
     * @inheritdoc
     */
    public function setData($field, $data)
    {
        //switch($field) {
        //case 'env':
        //    $this->client->setEnvironment($data);
        //    break;
        //case 'tags':
        //    $this->client->tags_context($data);
        //    break;
        //case 'user':
        //    $this->client->user_context($data);
        //    break;
        //case 'extra':
        //    $this->client->extra_context($data);
        //    break;
        //default:
        //    $msg = sprintf('Unknown field %s passed to %s.', $field, __FUNCTION__);
        //    throw new RollbarLogWriterException($msg);
        //}
    }

    /**
     * Simple accessor for data set to / on the client.
     *
     * @return array
     */
    public function getData()
    {
        return [
            'env'   => $this->client->getEnvironment(),
            'tags'  => $this->client->context->tags,
            'user'  => $this->client->context->user,
            'extra' => $this->client->context->extra,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLevel($level)
    {
        return isset($this->client->logLevels[$level]) ?
            $this->client->logLevels[$level] :
            $this->client->logLevels[self::$default_error_level];
    }

    /**
     * @inheritdoc
     */
    public function send($message, $extras = [], $data, $trace)
    {
        // Raven_Client::captureMessage() returns an ID to identify each message
        $eventId = $this->client->captureMessage($message, $extras, $data, $trace);

        return $eventId ?: false;
    }

}
