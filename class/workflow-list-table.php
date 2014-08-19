<?php 

if(!class_exists('WP_List_Table')){
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Workflow_List_Table extends WP_List_Table {
    var $_columns;
    
    function __construct(){
        $this->_create_columns();
        $this->_args = array();
        $this->items = array();
    }
    
    function _create_columns(){        
        parent::__construct(array(
            'singular' => __('item in workflow', 'approval-workflow'),
            'plural' => __('items in workflow', 'approval-workflow'),
            'ajax' => false
        ));
        
        $this->_columns = array(
            //new WP_List_Table_Column('cb', '<input type="checkbox" />'),
            new WP_List_Table_Column('title', __('Title', 'approval-workflow'), true),
            new WP_List_Table_Column('post_type', __('Post Type', 'approval-workflow'), true),
            new WP_List_Table_Column('site', __('Site', 'approval-workflow'), true, true),
            new WP_List_Table_Column('last_modified', __('Date Modified', 'approval-workflow'), true),
        );
    }
    
    function column_cb($item) {
        return '<input type="checkbox" name="approval_objects[]" value="'. $item['ID'] .'" />';
    }
    
    function column_default($item, $column_name){
        foreach($this->_columns as $column){
            if($column_name == $column->name){
                return $item[$column_name];
            }
        }
        
        // Should hopefully not make it here
        return $column_name . __(' column is missing from Workflow_List_Table::column_default()');
    }
    
    function column_last_modified($item){
        return $item['last_modified_text'];
    }
    
    function column_post_type($item){
        $text = $item['post_type'];
        
        $actions = array(
            'view_all' => '<a href="'. $item['post_type_link'] .'">' . __('View All', 'approval-workflow') . '</a>',
        );
        $text .= $this->row_actions($actions);
        
        return $text;
    }
    
    function column_site($item){
        $text = $item['site'];
        
        $actions = array(
            'visit_site' => '<a href="'. $item['site_link'] .'">' . __('Visit Site', 'approval-workflow') . '</a>',
            'visit_dashboard' => '<a href="'. $item['site_dashboard_link'] .'">' . __('Dashboard', 'approval-workflow') . '</a>'
        );
        $text .= $this->row_actions($actions);
        
        return $text;
    }
    
    function column_title($item){
        $text = '<strong>' . $item['title_link'] . '</strong>';
        
        $actions = array(
            'view_changes' => '<a href="'. $item['compare_link'] .'">' . __('Compare Changes', 'approval-workflow') . '</a>',
            'edit' => '<a href="'. $item['edit_link'] .'">' . __('Edit', 'approval-workflow') . ' ' . $item['post_type'] . '</a>'
        );
        $text .= $this->row_actions($actions);
        
        return $text;
    }
    
    /*function get_bulk_actions(){
        $actions = array(
            'approve' => __('Approve', 'approval-workflow'),
        );
        
        return $actions;
    }*/

    function get_columns(){
        global $approval_workflow;
        
        $columns = array();
        foreach($this->_columns as $column){
            // If not in multisite, don't include multisite columns
            if($column->multisite_only && $approval_workflow->is_network == false){
                continue; 
            }
            
            $columns[$column->name] = $column->label;
        }
        
        return $columns;
    }
    
    function get_sortable_columns(){
        $sortable_columns = array();
        foreach($this->_columns as $column){
            if($column->is_sortable){
                $sortable_columns[$column->name] = array($column->name, false);
            }
        }
        
        return $sortable_columns;
    }
    
    function no_items(){
        _e('No items in the workflow.', 'approval-workflow');
    }
    
    function prepare_items(){
        global $wpdb, $blog_id, $approval_workflow;
        
        $date_format = get_option('date_format') . " " . get_option('time_format');
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        if($approval_workflow->is_network){
	        // Grab list of items in workflow
	        $query = "SELECT blog_id FROM " . $wpdb->base_prefix . "blogs WHERE spam != '1' AND archived != '1' AND deleted != '1' ORDER BY last_updated ASC";
	        $query = apply_filters('approval_workflow_sites_edit_query', $query);
	        $blogs = $wpdb->get_col($query);
	        
	        $old_blog_id = $blog_id;
	        foreach($blogs as $blog){
	            switch_to_blog($blog);
	            
	            $this->_get_workflow_items($blog, $date_format);
	        }
	        switch_to_blog($old_blog_id);
        } else {
        	$this->_get_workflow_items(1, $date_format);
        }
        
        usort($this->items, array(&$this, 'usort_reorder'));
    }
    
    function _get_workflow_items($blog, $date_format){
    	global $wpdb;
    	
    	$post_types = get_post_types(array(), 'objects');
    	$query = "SELECT p.* FROM " . $wpdb->prefix . "posts p INNER JOIN " . $wpdb->prefix . "postmeta m ON m.post_id = p.ID WHERE m.meta_key = '_waiting_for_approval' AND m.meta_value = '1' AND p.post_status <> 'trash'";
    	$results = $wpdb->get_results($query);
    	if(!empty($results)){
    		$posts[$blog] = $results;
    		foreach($results as $post){
    		    $revisions = wp_get_post_revisions($post->ID, array('posts_per_page' => 1));
    			$last_revision = array_pop($revisions);
    			$revision_compare_link = admin_url( 'revision.php?action=diff&post_type=' . $post->post_type . '&right=' . $post->ID . '&left=' . $last_revision->ID );
    			 
    			$this->items[] = array(
    					'ID' => $post->ID,
    					'title' => $post->post_title,
    					'title_link' => '<a href="'. $revision_compare_link .'" title="'. __('Compare Changes') .'">' . $post->post_title . '</a>',
    					'post_type' => $post_types[$post->post_type]->labels->singular_name,
    					'post_type_link' => get_bloginfo('url') . '/wp-admin/edit.php?post_type=' . $post->post_type,
    					'site' => get_bloginfo('name'),
    					'site_link' => '<a href="'. get_bloginfo('url') .'">' . get_bloginfo('name') . '</a>',
    					'last_modified' => $post->post_modified,
    					'last_modified_text' => date_i18n($date_format, strtotime($post->post_modified)),
    					'compare_link' => $revision_compare_link,
    					'edit_link' => get_edit_post_link($post->ID),
    					'site_link' => get_bloginfo('url')  . '/',
    					'site_dashboard_link' => get_bloginfo('url') . '/wp-admin/',
    			);
    		}
    	}
    }
    
    function usort_reorder($a, $b){
        // If no sort, default to last modified date
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'last_modified';
        
        // If no order, deafult to asc
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
        
        $result = strcmp($a[$orderby], $b[$orderby]);
        
        // Send final direction to usort
        return ($order === 'asc') ? $result : -$result;
    }
}

class WP_List_Table_Column {
    var $name;
    var $label;
    var $is_sortable;
    var $multisite_only;
    
    function __construct($name, $label, $is_sortable = false, $multisite_only = false){
        $this->name = $name;
        $this->label = $label;
        $this->is_sortable = $is_sortable;
        $this->multisite_only = $multisite_only;
    }
}
?>