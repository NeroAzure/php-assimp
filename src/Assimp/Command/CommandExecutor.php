<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2013 Marco Graetsch <magdev3.0@googlemail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author    magdev
 * @copyright 2013 Marco Graetsch <magdev3.0@googlemail.com>
 * @package
 * @license   http://opensource.org/licenses/MIT MIT License
 */


namespace Assimp\Command;

use Assimp\ErrorCodes;
use Assimp\Command\Verbs\VerbInterface;
use Assimp\Command\Verbs\CacheableVerbInterface;

/**
 * Assimp Command Executor
 *
 * @author magdev
 */
class CommandExecutor
{
    /** @var string */
    private $bin = null;

    /** @var array */
    private static $cache = array();


    /**
     * Constructor
     *
     * @param string $bin
     */
    public function __construct($bin = null)
    {
        if (is_string($bin)) {
            $this->setBinary($bin);
        }
    }


    /**
     * Execute a command
     *
     * @param VerbInterface $verb
     * @return boolean
     */
    public function execute(VerbInterface $verb)
    {
        try {
        	if ($verb instanceof CacheableVerbInterface) {
        		$cachedVerb = self::getCached($verb);
        		if ($cachedVerb) {
        			return $cachedVerb;
        		}
        	}

        	$results = array();
        	$exitCode = null;
            $cmd = $this->getBinary().' '.$verb->getCommand();
            $verb->setExecutedCommand($cmd);
	        exec($cmd, $results, $exitCode);

            $verb->setExitCode($exitCode)
                ->setResults($results);

            if ($verb instanceof CacheableVerbInterface) {
            	self::addCached($verb);
            }

        } catch (\Exception $e) {
            $verb->setException($e);
        }

        return $verb->isSuccess();
    }


    /**
     * Get the path to the assimp binary
     *
     * @return string
     * @throws \RuntimeException
     */
    public function getBinary()
    {
        if (is_null($this->bin)) {
        	$paths = array('/usr/bin/assimp', '/usr/local/bin/assimp', '~/bin/assimp');
        	foreach ($paths as $path) {
        		try {
        			$this->setBinary($path);
        		} catch (\InvalidArgumentException $e) {}
        	}

        	if (!$this->bin) {
        		throw new \RuntimeException('assimp-binary not found', ErrorCodes::FILE_NOT_FOUND);
        	}
        }
        return $this->bin;
    }


    /**
     * Set the path to the assimp binary
     *
     * @param string $bin
     * @throws \InvalidArgumentException
     * @return \Assimp\Command\CommandExecutor
     */
    public function setBinary($bin)
    {
        if (!is_file($bin)) {
            throw new \InvalidArgumentException('Binary file not exists: '.$bin, ErrorCodes::FILE_NOT_FOUND);
        }
        if (!is_executable($bin)) {
            throw new \InvalidArgumentException('Binary file is not executable: '.$bin, ErrorCodes::FILE_NOT_EXECUTABLE);
        }
        $this->bin = $bin;
        return $this;
    }


    /**
     * Get a cached version of the verb
     *
     * @param \Assimp\Command\Verbs\CacheableVerbInterface $verb
     * @return \Assimp\Command\Verbs\CacheableVerbInterface|NULL
     */
    private static function getCached(CacheableVerbInterface $verb)
    {
    	$key = $verb->getCacheKey();
    	if (array_key_exists($key, self::$cache)) {
    		return self::$cache[$key];
    	}
    	return null;
    }


    /**
     * Add a verb to the cache
     *
     * @param \Assimp\Command\Verbs\CacheableVerbInterface $verb
     * @return void
     */
    private static function addCached(CacheableVerbInterface $verb)
    {
    	self::$cache[$verb->getCacheKey()] = $verb;
    }
}