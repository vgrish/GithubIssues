<?php

class GithubIssues
{
    /** @var array $bench An array of running benches */
    public $bench = array();
    /** @var array $chunks A collection of preprocessed chunk values. */
    protected $chunks;
    /** @var modX $modx A reference to the modX object. */
    public $modx;
    /** @var array $config A collection of properties to adjust Object behaviour. */
    public $config = array();
    /** @var string $prefix The component prefix, mostly used during dev */
    public $prefix;
    /** @var \Github\Client */
    public $client;

    /**
     * Constructs the GithubIssues object
     *
     * @param modX &$modx A reference to the modX object
     * @param array $config An array of configuration options
     */
    function __construct(modX &$modx, array $config = array())
    {
        $this->modx =& $modx;
        $this->prefix = $prefix = strtolower(get_class($this));

        $basePath = $this->modx->getOption("{$prefix}.core_path", $config, $this->modx->getOption('core_path') . "components/{$prefix}/");

        $this->config = array_merge(array(
            'core_path' => $basePath,
            'model_path' => $basePath . 'model/',
            'chunks_path' => $basePath . 'elements/chunks/',
            'chunks_suffix' => '.html',
            'add_package' => true,

            'use_autoloader' => true,
            'vendor_path' => $basePath . 'vendor/',

            'debug' => $this->modx->getOption("{$prefix}.debug", null, false),
            'debug_user' => null,
            'debug_user_id' => null,
        ), $config);

        $this->modx->lexicon->load('githubissues:default');
        if ($this->modx->getOption('debug', $this->config)) $this->initDebug();
        if ($this->config['use_autoloader']) $this->autoLoad();
    }

    /**
     * Initialize the debug properties, to get more verbose errors
     *
     * @return void
     */
    private function initDebug()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', true);
        //$this->modx->setLogTarget('FILE');
        $this->modx->setLogLevel(modX::LOG_LEVEL_INFO);

//        $debugUser = !isset($this->config['debug_user']) ? $this->modx->user->get('username') : 'anonymous';
//        $user = $this->modx->getObject('modUser', array('username' => $debugUser));
//        if ($user == null) {
//            $this->modx->user->set('id', $this->modx->getOption('debug_user_id', $this->config, 1));
//            $this->modx->user->set('username', $debugUser);
//        } else {
//            $this->modx->user = $user;
//        }
    }

    /**
     * Initialize the auto-loader if found
     *
     * @return void
     */
    private function autoLoad()
    {
        $loader = $this->config['vendor_path'] . 'autoload.php';
        if (file_exists($loader)) {
            require_once $loader;
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Autoloader file not found');
        }
    }

    /**
     * Return this service class configuration.
     * Unset sensitive data here if required.
     *
     * @return array The service class configuration
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Returns an instance of Github\Client
     *
     * @param mixed $token Optional API token
     *
     * @return \Github\Client
     */
    public function getClient($token = false)
    {
        if (!$this->client) {
            if (!$token) {
                $token = $this->modx->getOption('githubissues.token');
            }
            $this->client = new Github\Client();
            $this->client->authenticate($token, null, Github\Client::AUTH_URL_TOKEN);
        }

        return $this->client;
    }

    /**
     * Gets a Chunk; also falls back to file-based templates
     * for easier debugging.
     *
     * @param string $name The name of the Chunk
     * @param array $properties The properties for the Chunk
     *
     * @return string The processed content of the Chunk
     */
    public function getChunk($name, $properties = array())
    {
        $chunk = null;
        if (!isset($this->chunks[$name])) {
            $chunk = $this->getTplChunk($name, $properties);
            if (empty($chunk)) {
                $chunk = $this->modx->getObject('modChunk', array('name' => $name));
                if ($chunk == false) return false;
            }
            $this->chunks[$name] = $chunk->getContent();
        }

        $o = $this->chunks[$name];
        $chunk = $this->modx->newObject('modChunk');
        $chunk->setContent($o);
        $chunk->setCacheable(false);

        return $chunk->process($properties);
    }

    /**
     * Returns a modChunk object from a template file.
     *
     * @param string $name The name of the Chunk. Will parse to name.$postfix
     * @param string $postfix The default postfix to search for chunks at.
     *
     * @return modChunk|boolean Returns the modChunk object if found, otherwise
     * false.
     */
    private function getTplChunk($name, array $properties = array())
    {
        $chunk = false;
        $suffix = $this->modx->getOption('chunks_suffix', $properties, $this->config['chunks_suffix']);
        $path = $this->modx->getOption('chunks_path', $properties, $this->config['chunks_path']);
        $f = $path . strtolower($name) . $suffix;
        if (file_exists($f)) {
            $o = file_get_contents($f);
            /** @var $chunk modChunk */
            $chunk = $this->modx->newObject('modChunk');
            $chunk->set('name', $name);
            $chunk->setContent($o);
        }

        return $chunk;
    }

    /**
     * @return mixed
     */
    public function getMicrotime()
    {
        $mtime = microtime();
        $mtime = explode(' ', $mtime);
        $mtime = $mtime[1] + $mtime[0];

        return $mtime;
    }

    /**
     * Starts a bench timer
     *
     * @param string $name The bench name
     *
     * @return void
     */
    public function startBench($name)
    {
        $this->bench[$name] = $this->getMicrotime();
    }

    /**
     * Stops the given bench
     *
     * @param string $name The bench name
     *
     * @return string The bench result
     */
    public function endBench($name)
    {
        $tend = $this->getMicrotime();
        $totalTime = ($tend - $this->bench[$name]);
        $result = sprintf("Exec time for %s * %2.4f s", $name, $totalTime);
        $result .= " - Peak memory usage: " . (memory_get_peak_usage(true) / 1024 / 1024) . " MB\n";

        return $result;
    }

}
