<?php
/**
 * Arikaim
 *
 * @link        http://www.arikaim.com
 * @copyright   Copyright (c)  Konstantin Atanasov <info@arikaim.com>
 * @license     http://www.arikaim.com/license
 * 
*/
namespace Arikaim\Core\Options;

use Arikaim\Core\Collection\Collection;
use Arikaim\Core\Utils\Utils;
use Arikaim\Core\Utils\Number;
use Arikaim\Core\Interfaces\OptionsStorageInterface;
use Arikaim\Core\Interfaces\OptionsInterface;
use Arikaim\Core\Interfaces\CacheInterface;

/**
 * Options base class
 */
class Options extends Collection implements OptionsInterface
{ 
    /**
     * Save cache time
     *
     * @var integer
     */
    public static $cacheSaveTime = 10;

    /**
     * Should reload options array
     *
     * @var boolean
     */
    protected $needReload;
    
    /**
     * Storage adapter
     *
     * @var OptionsStorageInterface|null
     */
    protected $adapter;

    /**
     * Cache
     *
     * @var CacheInterface
     */
    protected $cache;

    /**
    * Constructor
    *
    * @param OptionsStorageInterface $adapter
    * @param CacheInterface $cache
    * @param bool $disabled
    */
    public function __construct(CacheInterface $cache, OptionsStorageInterface $adapter = null) 
    {  
        $this->cache = $cache;
        $this->adapter = $adapter;
        $this->needReload = true;
        
        Self::$cacheSaveTime = \defined('CACHE_SAVE_TIME') ? \constant('CACHE_SAVE_TIME') : Self::$cacheSaveTime;

        parent::__construct([]);             
    }

    /**
     * Set storage adapter
     *
     * @param OptionsStorageInterface $adapter
     * @return void
     */
    public function setStorageAdapter(OptionsStorageInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Store options in collection 
     *
     * @return void
     */
    public function load(): void
    {
        $options = $this->cache->fetch('options');
        if (empty($options) == true) {              
            $options = (empty($this->adapter) == false) ? $this->adapter->loadOptions() : [];
            $this->cache->save('options',$options,Self::$cacheSaveTime);
        }
    
        $this->data = $options;
        $this->needReload = false;
    }

    /**
     * Create option, if option exists return false
     *
     * @param string $key
     * @param mixed $value
     * @param boolean $autoLoad
     * @param string|null $extension
     * @return boolean
    */
    public function createOption(string $key, $value, bool $autoLoad = false, ?string $extension = null): bool
    {
        $result = (empty($this->adapter) == false) ? $this->adapter->createOption($key,$value,$autoLoad,$extension) : false;
        if ($result !== false) {
            $this->data[$key] = $value;
        }

        return $result;
    }

    /**
     * Save option
     *
     * @param string $key
     * @param mixed $value   
     * @param string|null $extension
     * @return bool
     */
    public function set(string $key, $value, $extension = null)
    {
        $result = (empty($this->adapter) == false) ? $this->adapter->saveOption($key,$value,$extension) : false;
        if ($result !== false) {
            // clear options cache           
            $this->cache->delete('options');        
            $this->data[$key] = $value;
        }

        return $result;
    }

    /**
     * Return true if option name exist
     *
     * @param string $key
     * @return boolean
    */
    public function has(string $key): bool
    {
        return (empty($this->adapter) == false) ? $this->adapter->hasOption($key) : false;
    }

    /**
     * Get option
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
    */
    public function get(string $key, $default = null)
    {
        if ($this->needReload == true) {
            $this->load();
        }
        if (isset($this->data[$key]) == false) {
            $value = (empty($this->adapter) == false) ? $this->adapter->read($key,$default) : $default;
            $this->data[$key] = $value;
        }
        $result = $this->data[$key] ?? $default;
              
        return $this->resolveOptionType($result);
    }

    /**
     * Force load option
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function read(string $key, $default = null)
    {
        $value = (empty($this->adapter) == false) ? $this->adapter->read($key,$default) : $default;
        $this->data[$key] = $value;

        return $value;
    } 

    /**
     * Remove option(s)
     *
     * @param string $key
     * @param string|null $extension
     * @return bool
    */
    public function removeOptions($key = null, $extension = null)
    {
        $result = (empty($this->adapter) == false) ? $this->adapter->remove($key,$extension) : false;
        $this->needReload = true;     
    
        return $result;
    }

    /**
     * Search options
     *
     * @param string $searchKey
     * @param bool $compactKeys
     * @return array
     */
    public function searchOptions($searchKey, $compactKeys = false)
    {
        $options = (empty($this->adapter) == false) ? $this->adapter->searchOptions($searchKey,$compactKeys) : [];
        
        return $this->resolveOptions($options);
    }

    /**
     * Get extension options
     *
     * @param string $extensioName
     * @return mixed
     */
    public function getExtensionOptions($extensioName)
    {
        return (empty($this->adapter) == false) ? $this->adapter->getExtensionOptions($extensioName) : [];
    }

    /**
     * Return collection array 
     *
     * @return array
     */
    public function toArray(): array
    {
        if ($this->needReload == true) {
            $this->load();
        }
        return $this->resolveOptions($this->data);
    }

    /**
     * Resolve option type
     *
     * @param mixed $value
     * @return mixed
     */
    protected function resolveOptionType($value)
    {
        if (\is_string($value) == true) {               
            $value = (Utils::isJson($value) == true) ? \json_decode($value,true) : $value;
        }
        if (\is_array($value) == true) {
            $value = $this->resolveOptions($value);
        }
        if (\is_string($value) == true) {               
            $value = (Number::isBoolean($value) == true) ? (bool)Number::toBoolean($value) : $value;
        }

        return $value;
    }

    /**
     * resolve options
     *
     * @param array $options
     * @return array
    */
    protected function resolveOptions(array $options)
    {
        foreach($options as $key => $value) {
            $options[$key] = $this->resolveOptionType($value);
        }

        return $options;
    } 
}
