<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Dev Docs Module CP
 * 
 * This add-on (so far) is filtered through a single method router
 * which uses a GET variable to determine which page to display
 * 
 * 
 * @package    Dev Docs
 * @author     Focus Lab, LLC <dev@focuslabllc.com>
 * @copyright  Copyright (c) 2011 Focus Lab, LLC
 * @link       https://github.com/focuslabllc/dev_docs.ee2_addon
 * @license    MIT  http://opensource.org/licenses/mit-license.php
 */

class Dev_docs_mcp {
	
	
	/**
	 * @var string  Base URL for inner-module linking
	 */
	private $_url_base;
	
	
	/**
	 * @var object  EE super object to be set in the constructor
	 */
	private $_EE;
	
	
	
	/**
	 * Class constructor
	 * 
	 * @access     public
	 * @author     Erik Reagan <erik@focuslabllc.com>
	 * @return     void
	 */
	public function __construct()
	{
		
		$this->_EE =& get_instance();
		
		// load some goodies
		$this->_EE->load->add_package_path(PATH_THIRD . 'dev_docs');
		$this->_EE->load->config('dev_docs');
		$this->_EE->load->model('dev_docs_model');
		$this->_EE->load->library('Docs_library');
		
		$this->_url_base = $this->_EE->config->item('dd:mod_url_base');
		
	}
	// End function __construct()
	
	
	
	
	/**
	 * Router
	 * 
	 * All "pages" for this module are routed through this method
	 * We check for the file
	 *
	 * @access     public
	 * @author     Erik Reagan <erik@focuslabllc.com>
	 * @return     string
	 */
	public function index()
	{
		
		// Start off with a consistent breadcrumb addition
		$name = ($this->_EE->config->item('dev_docs_cp_name')) ? $this->_EE->config->item('dev_docs_cp_name') : lang('dev_docs_module_name') ;
		$this->_EE->cp->set_breadcrumb($this->_url_base, $name);
		
		// Grab our developer documentation. Will be a setting / config override down the road.
		$file_path = APPPATH . 'third_party/dev_docs/views/samples/sample-directory/';
		$this->_EE->dev_docs_model->save_setting('file_path', $file_path);
		
		if ( ! file_exists($file_path))
		{
			show_error('The developer documentation file (' . $file_path . ') does not exist.
			            Eventually this will be a specific view file but it\'s early in the add-on\'s development.');
		}
		
		/**
		 * Cache check
		 * 
		 * Check the modification time on the flat file against the cached modification time. We save
		 * the last modification time in the database for this conditional. If the file has been updated
		 * since the last cache then we'll re-parse the file and save all sections to the DB. With this
		 * approach to caching the file contents it won't have to read and parse through the file(s) each
		 * time a page loads in the module.
		 */
		
		$path_changed = ($file_path != $this->_EE->dev_docs_model->get_setting('file_path')) ? TRUE : FALSE ;
		$file_updated = (filemtime($file_path) !== $this->_EE->dev_docs_model->get_setting('timestamp')) ? TRUE : FALSE ;
		$directory_mode = ($this->_EE->dev_docs_model->get_setting('doc_type') == 'dir') ? TRUE : FALSE ;
		$docs_exist = $this->_EE->dev_docs_model->docs_exist();
		
		// @todo - decide how to cache directory mode files
		if ( ! $docs_exist OR ($directory_mode === FALSE && ($path_changed OR $file_updated)) )
		{
			// delete doc rows
			$this->_EE->dev_docs_model->clear_current_docs();
			// Re-parse and re-save the docs
			$this->_EE->docs_library->parse_docs($file_path);
			// save new timestamp to DB
			$this->_EE->dev_docs_model->save_setting('timestamp', filemtime($file_path));
		}
		
		
		// Query to get our menu titles
		$pages = $this->_EE->dev_docs_model->get_pages('menu');
		foreach ($pages as $page) {
			$menu_array[$page['heading']] = $this->_url_base . AMP . 'docs_page=' . $page['short_name'];
		}
		
		
		/**
		 * Get our current page data
		 * 
		 * Query the DB for cached docs based on current page GET variable
		 * If we're on the homepage the parameter will be FALSE which the model method
		 * takes into consideration and just returns the first section of content
		 */
		$current_page = $this->_EE->dev_docs_model->get_page_contents($this->_EE->input->get('docs_page'));
		
		// Make sure we have pages to work with. If not, we'll display an error message
		if (empty($current_page))
		{
			show_error("Your documentation doesn't seem to have any valid content. Check your document(s) again.
			            <br><pre>" . $file_path . "</pre>");
		}
		
		// Build our submenu if applicable
		$current_submenu = $this->_EE->dev_docs_model->get_submenu($current_page->sub_dir, $current_page->file_name);
		
		// Some custom styles for better content display
		$theme_url = $this->_EE->config->item('theme_folder_url') . 'third_party/dev_docs/';
		$this->_EE->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . $theme_url . 'dev_docs.css" />');
		
		$this->_EE->cp->set_variable('cp_page_title', $current_page->heading);
		$this->_EE->cp->set_right_nav($menu_array);
		
		$data['submenu'] = $current_submenu;
		$data['content'] = $current_page->content;
		
		return $this->_EE->load->view('cp_index', $data, TRUE);
		
	}
	// End function index()
	
}
// End class Dev_docs_mcp

/* End of file mcp.dev_docs.php */
/* Location: ./system/expressionengine/third_party/dev_docs/mcp.dev_docs.php */