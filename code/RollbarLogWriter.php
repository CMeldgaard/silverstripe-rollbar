<?php

namespace silverstripe\rollbar;

use \Rollbar\Rollbar;
use \Rollbar\Payload\Level;
use \Rollbar\Config;
use \Rollbar\Payload\EncodedPayload;

require_once THIRDPARTY_PATH . '/Zend/Log/Writer/Abstract.php';

/**
 * The RollbarLogWriter class simply acts as a bridge between the configured Rollbar
 * adaptor and SilverStripe's {@link SS_Log}.
 *
 * Usage in your project's _config.php for example (See README for examples).
 *
 *    SS_Log::add_writer(\silverstripe\rollbar\RollbarLogWriter::factory(), '<=');
 */

class RollbarLogWriter extends \Zend_Log_Writer_Abstract
{

    /**
     * Stipulates what gets shown in the Rollbar UI, should some metric not be
     * available for any reason.
     *
     * @const string
     */
    const ROLL_NOOP = 'Unavailable';

    /**
     * A static constructor.
     *
     * @param  array $config    An array of optional additional configuration for
     *                          passing custom information to Rollbar. See the README for more detail.
     * @return RollbarLogWriter
     */
    public static function factory($config = [])
    {

        $env = isset($config['env']) ? $config['env'] : null;
        $tags = isset($config['tags']) ? $config['tags'] : [];
        $extra = isset($config['extra']) ? $config['extra'] : [];

        $writer = \Injector::inst()->get('RollbarLogWriter');

        // Set default environment
        if (is_null($env)) {
            $env = $writer->defaultEnv();
        }

        // Set all available user-data
        $userData = $writer->defaultUserData();

        // Set any available tags available in SS config
        $tags = array_merge($writer->defaultTags(), $tags);

        // Set any avalable additional (extra) data
        $extra = array_merge($writer->defaultExtra(), $extra);

        $writer->client->setData('env', $env);
        $writer->client->setData('user', $userData);
        $writer->client->setData('tags', $tags);
        $writer->client->setData('extra', $extra);

        return $writer;
    }

    /**
     * Returns a default environment when one isn't passed to the factory()
     * method.
     *
     * @return string
     */
    public function defaultEnv()
    {
        return \Director::get_environment_type();
    }

    /**
     * Returns a default set of additional data specific to the user's part in
     * the request.
     *
     * @param  Member $member
     * @return array
     */
    public function defaultUserData(\Member $member = null)
    {
        return [
            'IP-Address'    => $this->getIP(),
            'ID'            => $member ? $member->getField('ID') : self::ROLL_NOOP,
            'Email'         => $member ? $member->getField('Email') : self::ROLL_NOOP,
        ];
    }

    /**
     * Returns a default set of additional "tags" we wish to send to Sentry.
     * By default, Sentry reports on several mertrics, and we're already sending
     * {@link Member} data. But there are additional data that would be useful
     * for debugging via the Sentry UI.
     *
     * These data can augment that which is sent to Sentry at setup
     * time in _config.php. See the README for more detail.
     *
     * N.b. Tags can be used to group messages within the Sentry UI itself, so there
     * should only be "static" data being sent, not something that can drastically
     * or minutely change, such as memory usage for example.
     *
     * @return array
     */
    public function defaultTags()
    {
        return [
            'Request-Method'=> $this->getReqMethod(),
            'Request-Type'  => $this->getRequestType(),
            'SAPI'          => $this->getSAPI(),
            'SS-Version'    => $this->getPackageInfo('silverstripe/framework')
        ];
    }

    /**
     * Returns a default set of extra data to show upon selecting a message for
     * analysis in the Sentry UI. This can augment the data sent to Sentry at setup
     * time in _config.php as well as at runtime when calling SS_Log itself.
     * See the README for more detail.
     *
     * @return array
     */
    public function defaultExtra()
    {
        return [
            'Peak-Memory'   => $this->getPeakMemory()
        ];
    }

    /**
     * _write() forms the entry point into the physical sending of the error.
     *
     * @param  array $event An array of data that is created in, and arrives here
     *                      via {@link SS_Log::log()} and {@link Zend_Log::log}.
     * @return void
     */
    protected function _write($event)
    {
    	//Set error message
		$title = 'Err no. ' . $event['message']['errno'] . ': ' . $event['message']['errstr'];                             // From SS_Log::log()

		//Add line number and filename to error message, to make errors unique to each file
		$title .= ' - On line ' . $event['message']['errline'] . ' in ' . $event['message']['errfile'];

		//Get the debug backtrace
        $bt = debug_backtrace();

        // Use given context if available
        if (!empty($event['message']['errcontext'])) {
            $bt = $event['message']['errcontext'];
        }

        // Push current line into context
        array_unshift($bt, [
            'file' => $event['message']['errfile'],
            'line' => $event['message']['errline'],
            'function' => '',
            'class' => '',
            'type' => '',
            'args' => [],
        ]);

        $traces = \SS_Backtrace::filter_backtrace($bt, [
            'RollbarLogWriter->_write',
            'silverstripe\rollbar\RollbarLogWriter->_write'
        ]);


        $RollbarConfig = array(
        	'access_token' => 'f6cdd31fdcce4b0fb83002ece38c4ace',
		);

		$payload = array(
			'access_token' => 'f6cdd31fdcce4b0fb83002ece38c4ace',
			'data' => array(
				'uuid' => '',
				'environment' => $this->getEnv(),
				'timestamp' => strtotime($event['timestamp']),
				'framework' => 'Silverstripe framework' . $this->getPackageInfo('silverstripe/framework'),
				'body' => array(
					'trace' => array(
						'exception' => array(
							'class' => $event['message']['errstr']
						),
						'frames' => $this->getFrames($traces),
					)
				),
				'language' => 'PHP',
				'request' => array(
					'method' => $_SERVER['REQUEST_METHOD'],
					'url' => $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
					'headers' => array(
						'User-Agent' => $_SERVER['HTTP_USER_AGENT']
					),
					'user_ip' => $this->getIP(),
					'type' => $this->getRequestType(),
					'SAPI' => $this->getSAPI(),
					'memory_usage' => $this->getPeakMemory()
				),
				'server' => array(
					'branch' => $this->getGitBranch()
				),
				'title' => $title
			)
		);

		//Initialize rollbar config
		$Rollbar = new Config($RollbarConfig);

		//Send payload to encoder
		$PayloadEncode = new EncodedPayload($payload);
		//Encode the payload
		$PayloadEncode->encode();

		//Send the payload to rollbar
		$request = $Rollbar->send($PayloadEncode,'f6cdd31fdcce4b0fb83002ece38c4ace');

		//echo '<pre>';
		//print_r($request);
		//echo '</pre>';die;
    }

    public function getFrames($traces){

		$frames = [];

		//Generate traceback
		foreach ($traces as $trace){

			$method = '';

			if(isset($trace['class'])){
				$method .= $trace['class'] . '::';
			}

			if(isset($trace['function'])){
				$method .= $trace['function'];
			}

			$frames[] = array(
				'filename' => $trace['file'],
				'lineno' => $trace['line'],
				'method' => $method
			);
		}

		return $frames;

	}


	/**
	 * Returns either development or production depending on SS enviroment
	 *
	 * @return string
	 */
	public function getEnv()
	{
		$env = \Director::get_environment_type();

		switch ($env){
			case 'dev':
				return 'development';
			case 'live':
				return 'production';
		}

	}

	/**
	 * Get current Git branch that's checked out on the server
	 *
	 * @return string
	 */
	private function getGitBranch()
	{
		try {
			if (function_exists('shell_exec')) {
				$output = rtrim(shell_exec('git rev-parse --abbrev-ref HEAD'));
				if ($output) {
					return $output;
				}
			}
			return null;
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Return the version of $pkg taken from composer.lock.
	 *
	 * @param  string $pkg e.g. "silverstripe/framework"
	 * @return string
	 */
	public function getGitSHA()
	{
		//TODO
	}

    /**
     * Return the version of $pkg taken from composer.lock.
     *
     * @param  string $pkg e.g. "silverstripe/framework"
     * @return string
     */
    public function getPackageInfo($pkg)
    {
        $lockFileJSON = BASE_PATH . '/composer.lock';

        if (!file_exists($lockFileJSON) || !is_readable($lockFileJSON)) {
            return self::ROLL_NOOP;
        }

        $lockFileData = json_decode(file_get_contents($lockFileJSON), true);

        foreach ($lockFileData['packages'] as $package) {
            if ($package['name'] === $pkg) {
                return $package['version'];
            }
        }

        return self::ROLL_NOOP;
    }

    /**
     * Return the IP address of the relevant request.
     *
     * @return string
     */
    public function getIP()
    {
        $req = \Injector::inst()->create('SS_HTTPRequest', $this->getReqMethod(), '');

        if ($ip = $req->getIP()) {
            return $ip;
        }

        return self::ROLL_NOOP;
    }

    /**
     * What sort of request is this? (A harder question to answer than you might
     * think: http://stackoverflow.com/questions/6275363/what-is-the-correct-terminology-for-a-non-ajax-request)
     *
     * @return string
     */
    public function getRequestType()
    {
        $isCLI = $this->getSAPI() !== 'cli';
        $isAjax = \Director::is_ajax();

        return $isCLI && $isAjax ? 'AJAX' : 'Non-Ajax';
    }

    /**
     * Return peak memory usage.
     *
     * @return float
     */
    public function getPeakMemory()
    {
        $peak = memory_get_peak_usage(true) / 1024 / 1024;

        return (string) round($peak, 2) . 'Mb';
    }

    /**
     * Basic User-Agent check and return.
     *
     * @return string
     */
    public function getUserAgent()
    {
        $ua = @$_SERVER['HTTP_USER_AGENT'];

        if (!empty($ua)) {
            return $ua;
        }

        return self::ROLL_NOOP;
    }

    /**
     * Basic reuqest method check and return.
     *
     * @return string
     */
    public function getReqMethod()
    {
        $method = @$_SERVER['REQUEST_METHOD'];

        if (!empty($method)) {
            return $method;
        }

        return self::ROLL_NOOP;
    }

    /**
     * @return string
     */
    public function getSAPI()
    {
        return php_sapi_name();
    }

}
