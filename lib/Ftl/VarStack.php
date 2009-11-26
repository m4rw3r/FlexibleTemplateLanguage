<?php 
/*
 * Created on 2009 Jan 03
 * by Martin Wernstahl <m4rw3r@gmail.com>
 */

/**
 * An object with simulated properties, if property doesn't exists, it calls
 * the enclosed object.
 * 
 * @package Ftl_Parser
 * @author Martin Wernstahl <m4rw3r@gmail.com>
 * @copyright Copyright (c) 2009, Martin Wernstahl <m4rw3r@gmail.com>
 */
class Ftl_VarStack
{
	/**
	 * The data stored in this object.
	 * 
	 * @var array
	 */
	public $hash;
	
	/**
	 * Parent object.
	 * 
	 * @var Ftl_VarStack|null
	 */
	protected $object;
	
	function __construct(Ftl_VarStack $object = null)
	{
		$this->object = $object;
		$this->hash = array();
	}
	
	function &__get($property)
	{
		if(array_key_exists($property, $this->hash))
		{
			return $this->hash[$property];
		}
		
		if($this->object == null)
		{
			return null;
		}
		
		$prop =& $this->object->$property;
		return $prop;
	}
	
	function __set($property, $value)
	{
		$this->hash[$property] = $value;
	}
	
	function __isset($property)
	{
		return array_key_exists($property, $this->hash) ? true : isset($this->object->$property);
	}
	
	function __unset($property)
	{
		unset($this->hash[$property]);
	}
	
	function count_struct()
	{
		if(isset($this->object) && $this->object instanceof Ftl_VarStack)
		{
			return $this->object->count_struct() + 1;
		}
		
		return 1;
	}
}

/* End of file VarStack.php */
/* Location: ./lib/Ftl */