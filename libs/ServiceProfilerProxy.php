<?php

namespace Stayfilm\stayzen;

class ServiceProfilerProxy
{
	protected $instance;

	function __construct($object)
	{
		$this->instance = $object;
	}

	/**
	 *
	 * @param string $method
	 * @param array $arguments
	 * @return type
	 * @throws \Exception
	 */
	public function __call($method, $arguments)
	{

		if (method_exists($this->instance, $method))
		{
			$profiler = Profiler::getInstance();

			//info($this->instance->getServiceName() . "::$method())" . ">>>>>>>>>>>>>>>>>>\n" );
			$profiler->mark('start');
			$result = call_user_func_array(array($this->instance, $method), $arguments);
			$profiler->mark('end');
			//info("\n<<<<<<<<<<<<<<<<<< " . $this->instance->getServiceName() . "::$method())");
			$reflector = new \ReflectionClass($this->instance);
			$key = $reflector->getShortName() . "::$method()";

//			if ($method === 'dummy')
//			{
//
//				xdebug_print_function_stack();
//
//				echo "<pre>";
//				debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
//				echo "</pre>";
//				echo "<pre>";
//				print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
//				echo "</pre>";
//				exit;
//			}
			$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

			$profiler->add($key, 'start', 'end', $stack[1]);

			return $result;
		}
		else
		{
			throw new \Exception("Method DB::$method does not exist");
		}
	}

}