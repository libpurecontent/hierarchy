<?php

# Class to deal with hierarchical data structures
class hierarchy
{
	# Class properties
	private $data;
	private $error;
	private $children;
	
	# Determine the field names
	const PARENT_FIELDNAME = 'parentId';
	const CHILDREN_FIELDNAME = '_children';
	
	
	# Constructor
	public function __construct ($data, $rootNodeId = false /* To force the root to be from a certain node */)
	{
		# Register the data
		$this->data = $data;
		
		# Create the hierarchy
		$this->hierarchy = $this->createHierarchy ($rootNodeId);
		
	}
	
	
	# Accessor function to retrieve any error
	public function getError ()
	{
		return $this->error;
	}
	
	
	# Accessor function to return the hierarchy
	public function getHierarchy ()
	{
		return $this->hierarchy;
	}
	
	
	# Function to create a hierarchy from a flat data structure containing parentIds
	private function createHierarchy ($rootNodeId = false)
	{
		# End if there is no data
		if (!$this->data) {
			$this->error = 'There is no data';
			return false;
		}
		
		# End if there is not an array
		if (!is_array ($this->data)) {
			$this->error = 'The data is not an array';
			return false;
		}
		
		# Ensure every item has a parent field
		foreach ($this->data as $key => $item) {
			if (!isSet ($item[self::PARENT_FIELDNAME])) {
				$this->error = 'Not all items in the data have a parent defined';
				return false;
			}
		}
		
		# Ensure there is a single root node, defined as linking to itself
		if ($rootNodeId === false) {
			if (!$rootNodeId = $this->getRootNode ()) {
				$this->error = "There must be a single root node, but {$roots} were found.";
				return false;
			}
		}
		
		# Ensure every parent exists in the table
		foreach ($this->data as $key => $item) {
			$parent = $item[self::PARENT_FIELDNAME];
			if (!isSet ($this->data[$parent])) {
				$this->error = "Not all items in the data have a parent which exists. (Failure at ID {$key}).";
				return false;
			}
		}
		
		# Create a registry of children for each parent
		$this->childrenRegistry = $this->getChildrenRegistry ();
		
		# Traverse the array
		$hierarchy = array ();
		#!# Need to move this into the traversal - not sure why this doesn't work
		$hierarchy[$rootNodeId] = $this->data[$rootNodeId];
		$hierarchy[$rootNodeId][self::CHILDREN_FIELDNAME] = $this->hierarchyTraversal ($rootNodeId);
		
		# Ensure there is a single root node
		if (count ($hierarchy) != 1) {
			$this->error = 'There must be a single root node.';
			return false;
		}
		
		# Return the data
		return $hierarchy;
	}
	
	
	# Helper function to get the root node
	private function getRootNode ()
	{
		# Determine which items have the same parent ID as their own ID
		$roots = array ();
		foreach ($this->data as $key => $item) {
			if ($item[self::PARENT_FIELDNAME] == $key) {
				$roots[] = $key;
			}
		}
		
		# Ensure there is only one root node
		if (count ($roots) != 1) {
			return false;
		}
		
		# Retrieve the node
		$rootNodeId = $roots[0];
		
		# Return the root node
		return $rootNodeId;
	}
	
	
	# Helper function to get the children of a node
	private function getChildrenRegistry ()
	{
		# Create a list
		$children = array ();
		foreach ($this->data as $key => $item) {
			$parent = $item[self::PARENT_FIELDNAME];
			if ($parent == $key) {continue;}	// Skip the root
			$children[$parent][] = $key;
		}
		
		# Return the list of children
		return $children;
	}
	
	
	# Recursive function to traverse the hierarchy
	private function hierarchyTraversal ($currentNodeId)
	{
		# See if there are children for the current node
		if (!isSet ($this->childrenRegistry[$currentNodeId])) {return array ();}
		
		# Start a hierarchical structure to attach items to
		$hierarchy = array ();
		
		# For each child of the current node, add it to the hierarchy
		foreach ($this->childrenRegistry[$currentNodeId] as $childId) {
			$hierarchy[$childId] = $this->data[$childId];	// Add in the data
			$hierarchy[$childId][self::CHILDREN_FIELDNAME] = $this->hierarchyTraversal ($childId);
		}
		
		# Return the hierarchy
		return $hierarchy;
	}
	
	
	# Function to return whether a node exists
	public function nodeExists ($nodeId)
	{
		return (isSet ($this->data[$nodeId]) ? $this->data[$nodeId] : false);
	}
	
	
	# Function to get the children of a specified node
	public function childrenOf ($nodeId = false, $linkPattern = false, $nameOnly = true)
	{
		# End if the children registry is empty
		if (!$this->childrenRegistry) {
			return false;
		}
		
		# If no node is supplied, use the root node
		if (!$nodeId) {
			$nodeId = $this->getRootNode ();
		}
		
		# Get the children at this node
		if (!isSet ($this->childrenRegistry[$nodeId])) {
			return array ();
		}
		
		# Format as an associative array of array(id=>name,..) by looking this up in the original dataset
		$list = array ();
		foreach ($this->childrenRegistry[$nodeId] as $index => $childId) {
			$list[$childId] = ($nameOnly ? $this->data[$childId]['name'] : $this->data[$childId]);
			if ($linkPattern) {
				$list[$childId] = sprintf ("<a href=\"{$linkPattern}\">{$list[$childId]}</a>", $childId);
			}
		}
		
		# Return the list
		return $list;
	}
	
	
	# Function to get the direct family relevant to the current node, i.e. the current node, down the tree and up the tree (i.e. current node, children, grandchildren, etc., parent, grandparent, etc.) related to a specific node
	public function getFamily ($currentNodeId, $includeAncestors = true)
	{
		# Start an array of the family
		$family = array ();
		
		# Ensure the node exists, or end
		if (!isSet ($this->data[$currentNodeId])) {return array ();}
		
		# Add the current node
		$currentNode = array ($currentNodeId => $this->data[$currentNodeId]);
		$family += $currentNode;
		
		# Add the tree downwards (i.e. children, grandchildren, etc.)
		$descendants = $this->getDescendants ($currentNodeId);
		$family += $descendants;
		
		# Add the upwards values (i.e. parent, grandparent, etc.), unless disabled
		if ($includeAncestors) {
			$ancestors = $this->getAncestors ($currentNodeId);
			$family += $ancestors;
		}
		
		# Return the family
		return $family;
	}
	
	
	# Function to get the descendants of the current node (i.e. children, grandchildren, etc.)
	public function getDescendants ($currentNodeId)
	{
		# Start a list of descendants
		$descendants = array ();
		
		# Get the children for this node, or return an empty array if none
		if (!$children = $this->childrenOf ($currentNodeId, false, $nameOnly = false)) {return array ();}
		
		# Add these children
		$descendants += $children;
		
		# For each child, recursively iterate to get each child, grandchild, etc.
		foreach ($children as $nodeId => $child) {
			$descendants += $this->getDescendants ($nodeId);
		}
		
		# Return the descendants
		return $descendants;
	}
	
	
	# Function to get the ancestors of the current node (i.e. parent, grantparent, etc., all the way to the root), starting nearest-first (i.e. parent first, then grandparent, etc.)
	public function getAncestors ($currentNodeId, $includeCurrent = false)
	{
		# Start a list of ancestors
		$ancestors = array ();
		
		# If required, include the current
		if ($includeCurrent) {
			$ancestors[$currentNodeId] = $this->data[$currentNodeId];
		}
		
		# Get the start node's parent
		$parentId = $this->data[$currentNodeId]['parentId'];
		
		# Loop until no more data
		while (true) {
			
			# End if the specified parent does not exist
			$newParentId = $this->data[$parentId]['parentId'];
			if (!isSet ($this->data[$newParentId])) {break;}
			
			# End if the specified parent already exists in the list of ancestors (which indicates corrupted data)
			if (isSet ($ancestors[$newParentId])) {break;}
			
			# End if the specified parent is the same, i.e. is the root node
			if ($newParentId == $currentNodeId) {break;}
			
			# Add this item to the list
			$ancestors[$parentId] = $this->data[$parentId];
			
			# Specify the new parent
			$parentId = $newParentId;
		}
		
		# Return the ancestors
		return $ancestors;
	}
	
	
	# Function to get the nearest ancestor of the current node having a specified attribute value
	public function getNearestAncestorHavingAttributeValue ($currentNodeId, $attribute, $value, $returnRootIfNone = false, $includeCurrent = false)
	{
		# Get the ancestors of the current node
		$ancestors = $this->getAncestors ($currentNodeId, $includeCurrent);
		
		# Traverse up the hierarchy to find the nearest 'container' item, and return it if found
		foreach ($ancestors as $nodeId => $ancestor) {
			if ($ancestor[$attribute] == $value) {
				return $nodeId;
				break;
			}
		}
		
		# If required, return root if none
		if ($returnRootIfNone) {
			return $nodeId;
		}
		
		# Return false
		return false;
	}
	
	
	# Function to provide indented text as values for a <select> widget; see: http://stackoverflow.com/questions/10011194
	public static function asIndentedListing ($data, $indentString = '    ', /* private */ $level = 0)
	{
		# Start a list
		$list = array ();
		
		# Loop through the data
		foreach ($data as $key => $item) {
			$list[$key] = str_repeat ($indentString, $level) . $item['name'];
			if (isSet ($item[self::CHILDREN_FIELDNAME])) {
				$list += self::asIndentedListing ($item[self::CHILDREN_FIELDNAME], $indentString, ($level + 1));	// += maintains the keys
			}
		}
		
		# Return the list
	    return $list;
	}
	
	
	# Function to format a hierarchical list as a <ul>
	public static function asUl ($data, $linkBaseUrl, $additionLinks = false, $editLinks = false, $hidingProperty = false, $highlightId = false, $class = 'hierarchicallisting', $highlightingProperty = false, /* private */ $tabs = 0)
	{
		# Start a list of items
		$list = array ();
		
		# Loop through the data
		foreach ($data as $key => $item) {
			$list[$key] = htmlspecialchars ($item['name']);
			$highlightedId = ($highlightId && ($highlightId == $key));	// Current ID highlighting
			$highlightedEntry = ($highlightingProperty && isSet ($item[$highlightingProperty]) && $item[$highlightingProperty]);
			if ($highlightedId || $highlightedEntry) {
				$list[$key] = "<strong>{$list[$key]}</strong>";
			}
			$hiding = ($hidingProperty && isSet ($item[$hidingProperty]) && ($item[$hidingProperty]));
			if ($hiding) {
				$list[$key] = "<span>{$list[$key]}</span>";
			}
			
			# Add the entry
			$urlId = (isSet ($item['moniker']) ? $item['moniker'] : $key);	// Prefer URL monikers if supplied
			$icon = false;
			if ($editLinks) {
				if (isSet ($item['_hasEntry'])) {
					$icon = ($item['_hasEntry'] ? 'page' : 'page_white');
					if (isSet ($item['pageBreakBefore'])) {
						if ($item['pageBreakBefore']) {
							$icon = ($item['_hasEntry'] ? 'page_green' : 'page_white');
						}
					}
					$icon = "<img src=\"/images/icons/{$icon}.png\" alt=\"\" border=\"0\" /> ";
				}
			}
			$list[$key] = '<a' . (isSet ($item['_class']) && $item['_class'] ? " class=\"{$item['_class']}\"" : '') . " href=\"{$linkBaseUrl}" . (isSet ($item['_url']) ? $item['_url'] : "{$urlId}/") . "\">{$icon}{$list[$key]}</a>";
			if ($editLinks) {
				$list[$key] .= sprintf (" &nbsp;<a class=\"minilink\" href=\"{$linkBaseUrl}" . (isSet ($item['_editUrl']) ? $item['_editUrl'] : '') . ($editLinks ? $editLinks : '') . "\" title=\"Edit\">edit</a>", $urlId);
			}
			if ($additionLinks && !$hiding) {
				$list[$key] .= sprintf (" &nbsp;<a class=\"minilink\" href=\"{$linkBaseUrl}{$additionLinks}\" title=\"Add item within\">+</a>", $key);
			}
			
			# Traverse
			if (isSet ($item[self::CHILDREN_FIELDNAME])) {
				$list[$key] .= self::asUl ($item[self::CHILDREN_FIELDNAME], $linkBaseUrl, $additionLinks, $editLinks, $hidingProperty, $highlightId, false, $highlightingProperty, $tabs + 1);
			}
		}
		
		# Compile the HTML
		require_once ('application.php');
		$html = application::htmlUl ($list, $tabs, $class);
		
		# Return the HTML
	    return $html;
	}
}

?>
