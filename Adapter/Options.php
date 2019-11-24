<?php
/**
 * Arikaim
 *
 * @link        http://www.arikaim.com
 * @copyright   Copyright (c)  Konstantin Atanasov <info@arikaim.com>
 * @license     http://www.arikaim.com/license
 * 
*/
namespace Arikaim\Core\Options\Adapter;

use Illuminate\Database\Eloquent\Model;

use Arikaim\Core\Utils\Utils;
use Arikaim\Core\Collection\Arrays;
use Arikaim\Core\Options\OptionsInterface;

/**
 * Options database model
 */
class Options extends Model implements OptionsInterface
{    
    /**
     * Disable timestamps
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * Fillable attributes
     *
     * @var array
    */
    protected $fillable = [
        'key',
        'value',
        'auto_load',
        'extension'
    ];
    
    /**
     * Db table name
     *
     * @var string
     */
    protected $table = 'options';

    /**
     * Read option
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function read($key, $default = null) 
    {
        try {
            $model = $this->where('key','=',$key)->first();
            return (is_object($model) == false) ? $default : $model->value;  

        } catch(\Exception $e) {
        }

        return $default;
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
    public function createOption($key, $value, $autoLoad = false, $extension = null)
    {
        return ($this->hasOption($key) == true) ? false : $this->set($key,$value,$autoLoad,$extension);       
    }

    /**
     * Return true if option name exist
     *
     * @param string $key
     * @return boolean
     */
    public function hasOption($key)
    {
        $model = $this->where('key','=',$key)->first();

        return is_object($model);
    }

    /**
     * Save option
     *
     * @param string $key
     * @param mixed $value
     * @param boolean $autoLoad
     * @param string $extension
     * @return bool
     */
    public function set($key, $value, $autoLoad = false, $extension = null) 
    {
        $key = trim($key);
        if (empty($key) == true) {
            return false;
        }
        $key = str_replace('_','.',$key);
        
        if (is_array($value) == true) {            
            $value = Utils::jsonEncode($value,true);           
        }

        $data = [
            'key'       => $key,
            'value'     => $value,
            'auto_load' => ($autoLoad == true) ? 1 : 0,      
            'extension' => $extension
        ];
        
        try {
            if ($this->hasOption($key) == true) {
                $result = $this->where('key','=',$key)->update($data);
            } else {
                $result = $this->create($data);
            }
                  
        } catch(\Exception $e) {
            return false;
        }

        return $result;
    }

    /**
     * Load options
     *
     * @return Model
     */
    public function loadOptions()
    {        
        try {
            $model = $this->where('auto_load','=','1')->select('key','value')->get();
            if (is_object($model) == true) {
                $options = $model->mapWithKeys(function ($item) {
                    return [$item['key'] => $item['value']];
                })->toArray();
              
                return $options;
            }               
        } catch(\Exception $e) {
            
        }

        return [];
    }

    /**
     * Search for options
     *
     * @param string $searchKey
     * @return array
     */
    public function searchOptions($searchKey)
    {
        $options = [];
        $model = $this->where('key','like',"$searchKey%")->select('key','value')->get();
      
        if (is_object($model) == true) {
            $options = $model->mapWithKeys(function ($item) {
                return [$item['key'] => $item['value']];
            })->toArray(); 
        }     
        $values = Arrays::getValues($options,$searchKey);
        if (is_array($values) == false) {
            return [];
        }
        $result = null;
        foreach ($values as $key => $value) {
            $result = Arrays::setValue($result,$key,$value,'.');
        }      

        return $result;      
    }

    /**
     * Remove option
     *
     * @param string|null $key
     * @param string|null $extension
     * @return bool
     */
    public function remove($key = null, $extension = null)
    {
        $model = (empty($extension) == false) ? $this->where('extension','=',$extension) : $this;
        $model = (empty($key) == false) ? $this->where('key','=',$extension) : $model;

        $result = $model->delete();

        return $result;
    }
}
