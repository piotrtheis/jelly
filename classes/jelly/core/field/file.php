<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * Handles file uploads
 *
 * Since this field is ultimately just a varchar in the database, it
 * doesn't really make sense to put rules like Upload::valid or Upload::type
 * on the validation object; if you ever want to NULL out the field, the validation
 * will fail!
 *
 * @package    Jelly
 * @category   Fields
 * @author     Jonathan Geiger
 * @copyright  (c) 2010-2011 Jonathan Geiger
 * @license    http://www.opensource.org/licenses/isc-license.txt
 */
abstract class Jelly_Core_Field_File extends Jelly_Field implements Jelly_Field_Supports_Save
{
    
    public static $post_file_helper_prefix = 'post_file_helper_';

    /**
     * @var  boolean  whether or not to delete the old file when a new file is added
     */
    public $delete_old_file = TRUE;

    /**
     * @var  string  the path to save the file in
     */
    public $path = NULL;

    /**
     * @var  array  valid types for the file
     */
    public $types = array();

    /**
     * @var  string  the filename that will be saved
     */
    protected $_filename;

    /**
     * @var  boolean  file is automatically deleted if set to TRUE.
     */
    public $delete_file = FALSE;

    /**
     * Ensures there is a path for saving set.
     *
     * @param  array  $options
     */
    public function __construct($options = array())
    {
        parent::__construct($options);

        // Set the path
        $this->path = $this->_check_path($this->path);
    }

    /**
     * Adds a rule that uploads the file.
     *
     * @param   Jelly_Model  $model
     * @param   string       $column
     * @return void
     */
    public function initialize($model, $column)
    {
        parent::initialize($model, $column);

        if (count($this->rules) > 0)
        {
            // If rules can be found check if the rule for not_empty is set
            foreach ($this->rules as $rule)
            {
                if (is_string($rule[0]) AND $rule[0] === 'not_empty')
                {
                    $this->rules[] = array(array(':field', 'file_not_empty'), array(':validation', ':model', ':field'));
                }
            }
        }

        if ((bool) $this->types)
        {
            $this->rules[] = array(array(':field', 'file_invalid_type'), array(':validation', ':model', ':field', $this->types));
        }



        // Add a rule to save the file when validating
        $this->rules[] = array(array(':field', '_upload'), array(':validation', ':model', ':field'));
    }

    public static function file_not_empty(Validation $validation, $model, $field)
    {
        // Get the file from the validation object
        $file = $validation[$field];


        if (!is_array($file) OR ! isset($file['name']) OR empty($file['name']))
        {
            if (empty($_POST[self::$post_file_helper_prefix . $field]))
            {
                return FALSE;
            } else
            {
                return TRUE;
            }
            // Nothing uploaded
            return FALSE;
        }
    }

    public static function file_invalid_type(Validation $validation, $model, $field, $types)
    {
        // Get the file from the validation object
        $file = $validation[$field];

        if (empty($_POST[self::$post_file_helper_prefix . $field]))
        {
            // Check to see if it's a valid type
            if ($types AND ! Upload::type($file, $types))
            {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Implementation for Jelly_Field_Supports_Save.
     *
     * @param   Jelly_Model  $model
     * @param   mixed        $value
     * @param   boolean      $loaded
     * @return  void
     */
    public function save($model, $value, $loaded)
    {
        if ($this->_filename)
        {
            return $this->_filename;
        } else
        {
            if (is_array($value) AND empty($value['name']))
            {
                // Set value to empty string if nothing is uploaded
                $value = '';
            }

            return $value;
        }
    }

    /**
     * Deletes the file if automatic file deletion
     * is enabled.
     *
     * @param   Jelly_Model  $model
     * @param   mixed        $key
     * @return  void
     */
    public function delete($model, $key)
    {
        // Set the field name
        $field = $this->name;

        // Set file
        $file = $this->path . $model->$field;

        if ($this->delete_file AND is_file($file))
        {
            // Delete file
            unlink($file);
        }

        return;
    }

    /**
     * Logic to deal with uploading the image file and generating thumbnails according to
     * what has been specified in the $thumbnails array.
     *
     * @param   Validation   $validation
     * @param   Jelly_Model  $model
     * @param   Jelly_Field  $field
     * @return  bool
     */
     public function _upload(Validation $validation, $model, $field)
    {

        // Get the file from the validation object
        $file = $validation[$field];

        //no need upload if post helper isset
        if (!empty($_POST[self::$post_file_helper_prefix . $this->name]))
        {
            $this->_filename = $_POST[self::$post_file_helper_prefix . $this->name];

            return TRUE;
        }


        // Sanitize the filename
        $file['name'] = preg_replace('/[^a-z0-9-\.]/', '-', strtolower($file['name']));

        // Strip multiple dashes
        $file['name'] = preg_replace('/-{2,}/', '-', $file['name']);


        // Upload a file?
        if (($filename = Upload::save($file, NULL, $this->path)) !== FALSE)
        {
            // Standardise slashes
            $filename = str_replace('\\', '/', $filename);

            // Chop off the original path
            // $value = str_replace($this->path, '', $filename);
            $value = str_replace(ltrim($_SERVER['DOCUMENT_ROOT'], '/'), '', $filename);

            // Ensure we have no leading slash
            if (is_string($value))
            {
                $value = trim($value, '/');
            }

            // Garbage collect
            $this->_delete_old_file($model->original($this->name), $this->path);


            // Set the saved filename
            $this->_filename = '/' . $value;


            $_POST[self::$post_file_helper_prefix . $this->name] = $this->_filename;
            
           
        }

        return TRUE;
    }

    /**
     * Checks that a given path exists and is writable and that it has a trailing slash.
     *
     * (pulled out into a method so that it can be reused easily by image subclass)
     *
     * @param   string  $path
     * @return  string  the path - making sure it has a trailing slash
     */
    protected function _check_path($path)
    {
        // Normalize the path
        $path = str_replace('\\', '/', realpath($path));

        // Ensure we have a trailing slash
        if (!empty($path) AND is_writable($path))
        {
            $path = rtrim($path, '/') . '/';
        } else
        {
            throw new Kohana_Exception(get_class($this) . ' must have a `path` property set that points to a writable directory');
        }

        return $path;
    }

    /**
     * Deletes the previously used file if necessary.
     *
     * @param   string  $filename
     * @param   string  $path
     * @return  void
     */
    protected function _delete_old_file($filename, $path)
    {
        // Delete the old file if we need to
        if ($this->delete_old_file AND $filename != $this->default)
        {
            // Set the file path
            $path = $path . $filename;

            // Check if file exists
            if (file_exists($path))
            {
                // Delete file
                unlink($path);
            }
        }
    }

}

// End Jelly_Core_Field_File