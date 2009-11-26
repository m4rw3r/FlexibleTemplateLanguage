<?php 
/*
 * Created on 2009 Jan 02
 * by Martin Wernstahl <m4rw3r@gmail.com>
 */

/**
 * XML-like template parser.
 * 
 * @package Ftl_Parser
 * @author Martin Wernstahl <m4rw3r@gmail.com>
 * @copyright Copyright (c) 2009, Martin Wernstahl <m4rw3r@gmail.com>
 */
class Ftl_Parser
{
	/**
	 * The tag prefix.
	 * 
	 * @var string
	 */
	public $tag_prefix = 't';
	
	/**
	 * The context used to render the data tree.
	 * 
	 * @var XT_Context
	 */
	public $context;
	
	/**
	 * The parser stack.
	 * 
	 * @var array
	 */
	protected $stack;
	
	/**
	 * The current scope of the parsing, also the result of the parsing.
	 * 
	 * @var array
	 */
	protected $current;
	
	/**
	 * @param  Ftl_Context	The context to use
	 * @param  array 		The other options
	 */
	function __construct($context = false, $options = array())
	{
		if(is_array($context) && empty($options))
		{
			$options = $context;
			$context = isset($options['context']) ? $options['context'] : null;
		}
		
		$this->context = $context instanceof Ftl_Context ? $context : new Ftl_Context();
		$this->tag_prefix = isset($options['tag_prefix']) ? $options['tag_prefix'] : 't';
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * The parsing initializer.
	 * 
	 * @param  string  The string to parse
	 * @return string
	 */
	public function parse($text)
	{
		$tree = $this->generate_tree($text);
		
		return $this->render($tree);
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Generates a tree containing a parsed block structure.
	 * 
	 * @param  string
	 * @return array
	 */
	public function generate_tree($string)
	{
		unset($this->current);
		unset($this->stack);
		
		$this->current = array();
		$this->stack = array(array('content' => &$this->current));
		
		$this->pre_parse($string);
		
		return $this->current;
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Renders the block tree to a sigle string.
	 * 
	 * @param  array The block tree structure
	 * @return string
	 */
	public function render($tree)
	{
		$this->context->parser =& $this;
		
		$result = $this->compile($tree);
		
		unset($this->context->parser);
		
		return $result;
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Compiles the data tree, calling the context for all the tags.
	 *
	 * @param  array  The stack to parse
	 * @return string
	 */
	public function compile($stack)
	{
		if(empty($stack))
		{
			return '';
		}
		elseif(is_array($stack) && isset($stack['name']))
		{
			$stack = array($stack);
		}
		
		$str = '';
		foreach((Array) $stack as $element)
		{
			if(is_string($element))
			{
				$str .= $this->parse_individual($element);
			}
			elseif( ! empty($element))
			{
				$str .= $this->context->render_tag($element['name'], $element['args'], $element['content']);
			}
		}
		
		return $str;
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Parses all blocks into $this->current.
	 * 
	 * @param  string  The string to parse
	 * @return void
	 */
	protected function pre_parse($string)
	{
		$preg = preg_match('%([\w\W]*?)(<' . $this->tag_prefix . ':([\w:]+?)(\s+(?:\w+\s*=\s*(?:"[^"]*?"|\'[^\']*?\')\s*)*|)>|</' . $this->tag_prefix . ':([\w:]+?)\s*>)([\w\W]*)%', $string, $data);
		
		// actually faster with recursion instead of a while loop
		if ($preg)
		{
			list(, $pre_match, $match, $start_tag, $args, $end_tag, $post_match) = $data;
			$this->current[] = $pre_match;
			
			if($start_tag)
			{
				// create new block
				$data = array(
					'name' => $start_tag,
					'args' => $this->parse_args($args),
					'content' => array()
				);
				
				// add it to the tree
				$this->current[] =& $data;
				$this->stack[] =& $data;
				
				// move deeper
				$this->current =& $data['content'];
				
				// continue parsing
				$this->pre_parse($post_match);
			}
			else
			{
				// close the current block
				unset($this->current);
				$tmp =& array_pop($this->stack);
				
				if($end_tag == $tmp['name'])
				{
					// move up in the tree
					$parent =& $this->stack[count($this->stack) - 1];
					$this->current =& $parent['content'];
					
					// continue parsing
					$this->pre_parse($post_match);
				}
				else
				{
					throw new RuntimeException('Missing End tag for "'.$end_tag.'"');
				}
			}
		}
		else
		{
			$this->current[] = $string;
		}
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Parses all single tags (ends with " />").
	 * 
	 * @param  string  The string to parse
	 * @return string
	 */
	protected function parse_individual($string)
	{
		// actually faster with recursion instead of a while loop
		if(preg_match('@([\w\W]*?)(<'.$this->tag_prefix.':([\w:]+?)(\s+(?:\w+\s*=\s*(?:"[^"]*?"|\'[^\']*?\')\s*)*)/>)([\w\W]*)@', $string, $data))
		{
			list(,$pre_match, $match, $tag_name, $args, $post_match) = $data;
			
			$args = $this->parse_args($args);
			
			$replace = $this->context->render_tag($tag_name, $args);
			
			return $pre_match . $replace . $this->parse_individual($post_match);
		}
		
		return $string;
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Parses all arguments for a tag.
	 * 
	 * @param  string  The argument string
	 * @return array
	 */
	protected function parse_args($string)
	{
		$arguments = array();
		
		preg_match_all('@(\w+?)\s*=\s*(\'|")(.*?)\2@', $string, $matches, PREG_SET_ORDER);
		
		foreach($matches as $match)
		{
			$arguments[$match[1]] = $match[3];
		}
		
		return $arguments;
	}
}

/* End of file Parser.php */
/* Location: ./lib/Ftl.php */