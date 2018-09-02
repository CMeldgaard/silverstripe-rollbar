<?php

namespace silverstripe\rollbar\Adaptor;

/**
 * The RollbarClientAdaptor provides the base-class functionality for subclasses
 * to act as bridges between the PHP SDK and the RollbarLogWriter class itself.
 * Any {@link RollbarClientAdaptor} subclass should be able to be swapped-out and
 * used at any point.
 */

abstract class RollbarClientAdaptor extends \Object
{

    /**
     * @param mixed $opt
     * @return mixed
     */
    protected function getSettings($setting)
    {
		$settings = $this->config()->settings;

        if (!is_null($settings) && !empty($settings[$setting])) {
            return $settings[$setting];
        }

        return null;
    }

    /**
     * Set the data we need from the writer.
     *
     * @param string                 $field
     * @param mixed (string | array) $data
     */
    abstract public function setData($field, $data);

    /**
     * @return string
     */
    abstract public function getLevel($level);

    /**
     * Physically transport the data to the configured Sentry host.
     *
     * @param  string $message
     * @param  array  $extras
     * @param  array  $data
     * @param  string $trace
     * @return mixed
     */
    abstract public function send($message, $extras = [], $data, $trace);

}
