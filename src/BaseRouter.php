<?php
namespace Gt\Routing;

use Gt\Config\ConfigSection;
use Gt\Http\ResponseStatusException\ClientError\HttpNotAcceptable;
use Gt\Http\ResponseStatusException\ClientError\HttpNotFound;
use Negotiation\Accept;
use Negotiation\Negotiator;
use Psr\Http\Message\RequestInterface;
use Gt\Http\ResponseStatusException\Redirection\HttpFound;
use Gt\Http\ResponseStatusException\Redirection\HttpMovedPermanently;
use Gt\Http\ResponseStatusException\Redirection\HttpMultipleChoices;
use Gt\Http\ResponseStatusException\Redirection\HttpNotModified;
use Gt\Http\ResponseStatusException\Redirection\HttpPermanentRedirect;
use Gt\Http\ResponseStatusException\Redirection\HttpSeeOther;
use Gt\Http\ResponseStatusException\Redirection\HttpTemporaryRedirect;
use ReflectionClass;

abstract class BaseRouter {
	private Assembly $viewAssembly;
	private Assembly $logicAssembly;

	public function __construct(
		protected ?ConfigSection $routerConfig = null,
		Assembly $viewAssembly = null,
		Assembly $logicAssembly = null,
	) {
		$this->viewAssembly = $viewAssembly ?? new Assembly();
		$this->logicAssembly = $logicAssembly ?? new Assembly();
	}

	public function handleRedirects(
		Redirects $redirects,
		RequestInterface $request
	):void {
		$responseCode = $this->routerConfig?->getInt("redirect_response_code");

		$responseClass = match($responseCode) {
			300 => HttpMultipleChoices::class,
			301 => HttpMovedPermanently::class,
			302 => HttpFound::class,
			303 => HttpSeeOther::class,
			304 => HttpNotModified::class,
			307 => HttpTemporaryRedirect::class,
			default => HttpPermanentRedirect::class
		};

		$uri = $request->getUri()->getPath();

		foreach($redirects as $old => $new) {
			if($old === $uri) {
				throw new $responseClass($new);
			}
		}
	}

	public function route(RequestInterface $request):void {
		/** @var array<RouterCallback> $validCallbackArray */
		$validCallbackArray = [];
// Find all callbacks that match the current request, filling the valid callback
// array. Then, the "best" callback will be matched using content negotiation.
		foreach($this->reflectRouterCallbacks() as $routerCallback) {
			if(!$routerCallback->isAllowedMethod($request->getMethod())) {
				continue;
			}
			if(!$routerCallback->matchesPath($request->getUri()->getPath())) {
				continue;
			}
			if(!$routerCallback->matchesAccept($request->getHeaderLine("accept"))) {
				continue;
			}

			array_push($validCallbackArray, $routerCallback);
		}

		$bestRouterCallback = $this->negotiateBestCallback(
			$request,
			$validCallbackArray
		);

		if(!$bestRouterCallback) {
			throw new HttpNotAcceptable();
		}

// TODO: Call with the DI, so the callback can receive all the required params.
		$bestRouterCallback->call($this);
	}

	public function getLogicAssembly():Assembly {

	}

	public function getViewAssembly():Assembly {

	}

	protected function addToLogicAssembly(
		string $relativePath,
		?string $logicName = null
	):void {
		$this->addToAssembly(
			Assembly::TYPE_LOGIC,
			$relativePath,
			$logicName
		);
	}

	protected function addToViewAssembly(
		string $relativePath,
		?string $viewName = null
	):void {
		$this->addToAssembly(
			Assembly::TYPE_VIEW,
			$relativePath,
			$viewName
		);
	}

	private function addToAssembly(
		string $type,
		string $path,
		?string $name
	):void {

	}

	/**
	 * Use Reflection to find all callbacks on $this that have an HttpRoute
	 * Attribute associated, whether or not the route is valid.
	 * @return array<RouterCallback>
	 */
	private function reflectRouterCallbacks():array {
		/** @var array<RouterCallback> $routerCallbackArray */
		$routerCallbackArray = [];

		$class = new ReflectionClass($this);
		foreach($class->getMethods() as $method) {
			foreach($method->getAttributes() as $attribute) {
				$name = $attribute->getName();

				if(!is_a($name, HttpRoute::class, true)) {
					continue;
				}

				array_push(
					$routerCallbackArray,
					new RouterCallback($method, $attribute)
				);
			}
		}

		return $routerCallbackArray;
	}

	/** @param array<RouterCallback> $callbackArray */
	private function negotiateBestCallback(
		RequestInterface $request,
		array $callbackArray
	):?RouterCallback {
		$negotiator = new Negotiator();

		$bestQuality = -1;
		$bestCallback = null;
		foreach($callbackArray as $callback) {
			$allAcceptedTypes = $callback->getAcceptedTypes();

			/** @var Accept|null $currentBest */
			$currentBest = $negotiator->getBest(
				$request->getHeaderLine("accept"),
				$allAcceptedTypes
			);

			$quality = $currentBest?->getQuality() ?? 0;
			if($quality <= $bestQuality) {
				continue;
			}

			$bestQuality = $quality;
			$bestCallback = $callback;
		}

		return $bestCallback;
	}
}