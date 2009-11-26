<?php 
/*
 * Created on 2009 Jan 02
 * by Martin Wernstahl <m4rw3r@gmail.com>
 */

/**
 * A context which renders the tags for the parser.
 * 
 * @package Ftl_Parser
 * @author Martin Wernstahl <m4rw3r@gmail.com>
 * @copyright Copyright (c) 2009, Martin Wernstahl <m4rw3r@gmail.com>
 */
class Ftl_Context
{	
	/**
	 * Contains tag definitions.
	 * 
	 * @var array
	 */
	public $definitions = array();
	
	/**
	 * The global data.
	 * 
	 * @var Ftl_VarStack
	 */
	public $globals;
	
	/**
	 * A stack of the tag bindings.
	 * 
	 * @var Ftl_Binding
	 */
	protected $tag_binding_stack = array();
	
	// --------------------------------------------------------------------
		
	/**
	 * Init.
	 * 
	 * Creates a var_stack.
	 */
	function __construct()
	{
		$this->globals = new Ftl_VarStack();
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Defines a tag.
	 * 
	 * @param  string    The name of the tags (nestings are separated with ":")
	 * @param  callable  The function/method to be called
	 * @return void
	 */
	public function define_tag($name, $callable)
	{
		$this->definitions[$name] = $callable;
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Renders the tags with the name and args supplied.
	 * 
	 * @param  string  The tag name
	 * @param  array   The args
	 * @param  array   The nested block
	 */
	public function render_tag($name, $args = array(), $block = null)
	{
		// do we have a compund tag?
		if(($pos = strpos($name, ':')) != 0)
		{
			// split them and parse them separately, as if they are nested
			$name1 = substr($name, 0, $pos);
			$name2 = substr($name, $pos + 1);

			return $this->render_tag($name1, array(), array(
					'name' => $name2,
					'args' => $args,
					'content' => $block
				));
		}
		else
		{
			$qname = $this->qualified_tag_name($name);
			
			if(is_string($qname) && array_key_exists($qname, $this->definitions))
			{
				// render
				return $this->stack($name, $args, $block, $this->definitions[$qname]);
			}
			else
			{
				return $this->tag_missing($name, $args, $block);
			}
		}
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Traverses the stack and handles the bindings and var_stack(s).
	 * 
	 * @param  string	The tag name
	 * @param  array	The tag args
	 * @param  array 	The nested block
	 * @param  callable	The function/method to call
	 * @return string
	 */
	protected function stack($name, $args, $block, $call)
	{
		// get previous locals, to let the data "stack"
		$previous = end($this->tag_binding_stack);
		$previous_locals = $previous == null ? $this->globals : $previous->locals;
		
		// create the stack and binding
		$locals = new Ftl_VarStack($previous_locals);
		$binding = new Ftl_Binding($this, $locals, $name, $args, $block);
		
		$this->tag_binding_stack[$name] = $binding;
		
		// Check if we have a function or a method
		if(is_callable($call))
		{
			$result = call_user_func($call, $binding);
		}
		else
		{
			throw new RuntimeException('Error in definition of tag "'.$name.'", the associated callable cannot be called.');
		}
		
		// jump out
		array_pop($this->tag_binding_stack);
		
		return $result;
	}

	// --------------------------------------------------------------------

	/**
	 * Makes a qualified guess of the tag definition requested depending on the current nesting.
	 * 
	 * @param  string  The name of the tag
	 * @return string
	 */
	function qualified_tag_name($name)
	{
		$path_chunks = array_merge(array_keys($this->tag_binding_stack), array($name));
		$path = implode(':', $path_chunks);
		
		if( ! isset($this->definitions[$path]))
		{
			$possible_matches = array();
			
			foreach(array_keys($this->definitions) as $def)
			{
				if ($def == $name)
				{
					$possible_matches[$this->accuracy(explode(':', $def), $path_chunks)] = $def;
				}
			}
			
			sort($possible_matches);
			
			return end($possible_matches);
		}
		else
		{
			return $path;
		}
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Calculates how accurately a tag definition name matches the requested tag.
	 * 
	 * @param  array  The tag path to match
	 * @param  array  The current path
	 * @return int
	 */
	protected function accuracy($try, $path)
	{
		$acc = 1000;
		
		while( ! empty($try) && ! empty($path))
		{
			if(end($try) == end($path))
			{
				array_pop($try);
				array_pop($path);
				continue;
			}
			
			array_pop($path);
			$acc--;
		}
		
		if( ! empty($try))
		{
			return false;
		}
		else
		{
			return $acc;
		}
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Raises a tag missing error.
	 * 
	 * @param  string The tag name
	 * @param  array  The tag parameters
	 * @param  array  The nested block
	 * @return string Or abort if needed (default)
	 */
	public function tag_missing($name, $args = array(), $block = null)
	{
		throw new RuntimeException('Tag missing: "'.$name.'", scope: "'.$this->current_nesting().'".');
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Returns the state of the current render stack.
	 * 
	 * Useful from inside a tag definition. Normally just use XT_Binding::nesting().
	 * 
	 * @return string
	 */
	function current_nesting()
	{
		return implode(':', array_keys($this->tag_binding_stack));
	}
}

/* End of file Context.php */
/* Location: ./lib/Ftl */