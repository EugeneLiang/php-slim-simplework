<?php
error_reporting(E_ALL);
session_start();
date_default_timezone_set("Asia/Hong_Kong");
define('IN_APPS', true);

// Define constant
define('WWW_ROOT', dirname(__FILE__));
define('APP_ROOT', WWW_ROOT.'/app');
define('CACHE_ROOT', WWW_ROOT.'/cache');
define('CONFIG_ROOT', WWW_ROOT.'/config');
define('HOOK_ROOT', WWW_ROOT.'/hook');
define('LIB_ROOT', WWW_ROOT.'/lib');
define('LOCALE_ROOT', WWW_ROOT.'/locale');
define('LOG_ROOT', WWW_ROOT.'/log');
define('PUBLIC_ROOT', WWW_ROOT.'/public');
define('VENDOR_ROOT', WWW_ROOT.'/vendor');
define('HELPERS_ROOT', APP_ROOT.'/helpers');
define('ROUTERS_ROOT', APP_ROOT.'/routes');
define('MODELS_ROOT', APP_ROOT.'/models');
define('VIEWS_ROOT', APP_ROOT.'/views');
define('ROUTERS_MIDDLEWARES_ROOT', ROUTERS_ROOT.'/middlewares');

// Using the composer autloader
require VENDOR_ROOT.'/autoload.php';

// Import the class
use Slim\Slim;
use Slim\Views;
use Slim\Extras\Middleware\CsrfGuard;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Bridge\Twig\Extension\TranslationExtension;

// Initial global variable
$config = array();

// Import config file
require_once CONFIG_ROOT.'/common.php';
require_once CONFIG_ROOT.'/database.php';

// Configure database
ORM::configure($config['database']['dsn']);

if (empty($config['database']['username']) === false) {
	ORM::configure('username', $config['database']['username']);
	ORM::configure('password', $config['database']['password']);
}

if (substr(strtolower($config['database']['dsn']), 0, 5) === 'mysql') {
	ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
}

if (empty($config['database']['prefix']) === false) {
	Model::$auto_prefix_models = '\\'.$config['database']['prefix'].'\\';
}

// Initial slim framework
$app = new Slim(array(
	'mode'               => $config['common']['application_mode'],
	'view'               => new Views\Twig(),
	'templates.path'     => VIEWS_ROOT,
	'debug'              => $config['common']['enable_debug'],
	'log.enable'         => $config['common']['enable_log'],
	'log.path'           => LOG_ROOT,
	'cookies.lifetime'   => $config['common']['cookies_life_time'],
	'cookies.secret_key' => $config['common']['cookies_secret_key'],
));
$app->add(new CsrfGuard());

// Twig view settings
$view = $app->view();
$view->twigTemplateDirs = array(VIEWS_ROOT);
$view->parserOptions = array(
	'charset'          => 'utf-8',
	'cache'            => realpath(CACHE_ROOT.'/views'),
	'auto_reload'      => true,
	'strict_variables' => false,
	'autoescape'       => true
);
$view->parserExtensions = array(
	new Views\TwigExtension(),
);

// Load the locale file
if ($config['common']['enable_locale'] === true) {
	$app->container->singleton('locale', function() use ($config) {
		$translator = new Translator($config['common']['default_locale'], new MessageSelector());
		$translator->setFallbackLocale($config['common']['fallback_locale']);
		$translator->addLoader('array', new ArrayLoader());
		$translator->addLoader('yaml', new YamlFileLoader());

		$directories = array(LOCALE_ROOT);

		while(sizeof($directories)) {
			$directory = array_pop($directories);

			foreach(glob($directory."/*") as $file_path) {
				if (is_dir($file_path) === true) {
					array_push($directories, $file_path);
				}else if (is_file($file_path) === true && preg_match('/.(php|yaml)$/', $file_path) == true) {
					$path_info = pathinfo($file_path);

					$extension = $path_info['extension'];
					$resource  = $file_path;

					if (strtolower($path_info['extension']) == 'php') {
						$extension = "array";
						$resource  = require($resource);
					}

					$translator->addResource($extension, $resource, $path_info['filename']);
				}
			}
		}

    	return $translator;
	});

	// Register locale extension to TWIG
	array_push($view->parserExtensions, new TranslationExtension($app->locale));
}

// Register global session to TWIG
$view->getEnvironment()->addGlobal("session", $_SESSION);

// Auto import all hook, routers, models, views file
$directories = array(HOOK_ROOT, HELPERS_ROOT, ROUTERS_ROOT, ROUTERS_MIDDLEWARES_ROOT, MODELS_ROOT, VIEWS_ROOT);

while (sizeof($directories)) {
	$directory = array_pop($directories);

	foreach(glob($directory."/*") as $file_path) {
		if (is_dir($file_path) === true) {
			array_push($directories, $file_path);
		}elseif (is_file($file_path) === true && preg_match("/\.(php|inc)$/", $file_path) == true) {
			require_once $file_path;
		}
	}
}

// Define helper variable
$headers = $app->request()->headers();
$seo_uri = $app->request()->getResourceUri();
$root_uri = $app->request()->getRootUri();
$protocol = isset($_SERVER['HTTPS']) === true ? 'https' : 'http';
$site_url = $protocol.'://'.$headers['HOST'].$root_uri;

// Set helper variable for control flow
$app->config('config',   $config);
$app->config('site_url', $site_url);

// Set helper variable for template
$app->view()->setData('config',   $config);
$app->view()->setData('site_url', $site_url);

// Boot application
$app->run();
