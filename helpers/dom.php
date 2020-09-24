<?php

//Generic DOM element class
abstract class DOMobject {
		
		protected $cssPath = "../public_html/css/style.css"; // /css/style.css for deployement
		protected $jsPath = "../public_html/js/main.js"; // /js/main.js for deployment
    
    //String for the id of the element
    protected $id = "";
    
    //Array for the classes of the element
    protected $classes = [];
    
    //Array of all child elements and text contents of the element 
    protected $children = [];
    
    //Store the element's contentEditable value
    protected $editable = "";
    
    public function __construct($id = "") {
        $this->id = $id;
    }
    
    //Set the id of the element
    public function setId($id) {
        $this->id = $id;
    }
    
    //Add a class to the element
    public function addClass($class) {
        $this->classes[] = $class;
    }
    
    //Append DOMobject or string to children of element
    public function addChild($child) {
        $this->children[] = $child;
    }
    
    //Set the element to editable
    public function setEditable() {
    		$this->editable = "true";
    }
    
    //Return string representation of the element with given indent
    abstract public function render($indent);
    
    //Return string representation of all children elements
    protected function renderChildren($indent) {
        $html = "";
        foreach ($this->children as $child) {
            if (gettype($child) === "string") {
                $html .= $indent.$child."\n";
            } else {
                $html .= $child->render($indent);
            }
        }
        return $html;
    }
    
    //Return string representation of the id and class and contentEditable of the element in renderable form
    protected function renderIdClass() {
        $html = "";
        if ($this->id != "") {
            $html .= " id=\"".$this->id."\"";
        }
        if ($this->editable != "") {
        		$html .= " contentEditable=\"".$this->editable."\"";
        }
        if (count($this->classes) > 0) {
            $html .= " class=\"";
            foreach ($this->classes as $class) {
                $html .= $class." ";
            }
            $html .= "\"";
        }
        return $html;
    }
    
}

//div class
class DOMdiv extends DOMobject {
    
    //Constructor is specified for clarity, despite being the same as the parent's
    public function __construct($id = "") {
        parent::__construct($id);
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<div";
        $html .= $this->renderIdClass();
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</div>\n";
        return $html;
    }
    
}

//a class
class DOMa extends DOMobject {
    
    //Store the link location
    private $href = "";
    
    
    public function __construct($id = "", $href = "") {
        parent::__construct($id);
        $this->href = $href;
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<a";
        $html .= $this->renderIdClass();
        if ($this->href != "") {
            $html .= " href=\"".$this->href."\"";
        }
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</a>\n";
        return $html;
    }
    
}

//input class
class DOMinput extends DOMobject {
    
    //Store appropriate fields
    private $type = "";
    private $value = "";
    private $placeholder = "";
    private $accept = "";
    private $multiple = "";
    
    public function __construct($id = "", $type = "") {
        parent::__construct($id);
        $this->type = $type;
    }
    
    public function setValue($value) {
        $this->value = $value;
    }
    
    public function setPlaceholder($placeholder) {
        $this->placeholder = $placeholder;
    }
    
    public function setAccept($accept) {
    		$this->accept = $accept;
    }
    
    public function setMultiple($multiple) {
    		$this->multiple = $multiple;
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<input";
        if ($this->type != "") {
            $html .= " type=\"".$this->type."\"";
        }
        $html .= $this->renderIdClass();
        if ($this->value != "") {
            $html .= " value=\"".$this->value."\"";
        }
        if ($this->placeholder != "") {
            $html .= " placeholder=\"".$this->placeholder."\"";
        }
        if ($this->accept != "") {
            $html .= " accept=\"".$this->accept."\"";
        }
        if ($this->multiple != "") {
            $html .= " multiple=\"".$this->multiple."\"";
        }
        $html .= " >\n";
        return $html;
    }
    
}

//img class
class DOMimg extends DOMobject {

    //Store image source and alt tag
    private $src = "";
    private $alt = "";
    
    public function __construct($id = "", $src = "", $alt = "") {
        parent::__construct($id);
        $this->src = $src;
        $this->alt = $alt;
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<img";
        $html .= $this->renderIdClass();
        if ($this->src != "") {
            $html .= " src=\"".$this->src."\"";
        }
        $html .= " alt=\"".$this->alt."\" >\n";
        return $html;
    }
    
}

class DOMh1 extends DOMobject {

    //Constructor is specified for clarity, despite being the same as the parent's
    public function __construct($id = "") {
        parent::__construct($id);
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<h1";
        $html .= $this->renderIdClass();
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</h1>\n";
        return $html;
    }
    
}

class DOMh2 extends DOMobject {

    //Constructor is specified for clarity, despite being the same as the parent's
    public function __construct($id = "") {
        parent::__construct($id);
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<h2";
        $html .= $this->renderIdClass();
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</h2>\n";
        return $html;
    }
    
}

class DOMh3 extends DOMobject {

    //Constructor is specified for clarity, despite being the same as the parent's
    public function __construct($id = "") {
        parent::__construct($id);
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<h3";
        $html .= $this->renderIdClass();
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</h3>\n";
        return $html;
    }
    
}

class DOMp extends DOMobject {

    //Constructor is specified for clarity, despite being the same as the parent's
    public function __construct($id = "") {
        parent::__construct($id);
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<p";
        $html .= $this->renderIdClass();
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</p>\n";
        return $html;
    }
    
}

class DOMul extends DOMobject {

    //Constructor is specified for clarity, despite being the same as the parent's
    public function __construct($id = "") {
        parent::__construct($id);
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<ul";
        $html .= $this->renderIdClass();
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</ul>\n";
        return $html;
    }
    
}

class DOMli extends DOMobject {

    //Constructor is specified for clarity, despite being the same as the parent's
    public function __construct($id = "") {
        parent::__construct($id);
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<li";
        $html .= $this->renderIdClass();
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</li>\n";
        return $html;
    }
    
}

class DOMselect extends DOMobject {

    //Constructor is specified for clarity, despite being the same as the parent's
    public function __construct($id = "") {
        parent::__construct($id);
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<select";
        $html .= $this->renderIdClass();
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</select>\n";
        return $html;
    }
}

class DOMoption extends DOMobject {
    
    //Store appropriate fields
    private $value = "";
    private $selected = "";
    private $disabled = "";
    private $hidden = "";
    
    public function __construct($id = "", $value = "", $child = "") {
        parent::__construct($id);
        $this->value = $value;
        if ($child != "") {
            $this->addChild($child);
        }
    }
    
    //Set the option to selected
    public function select() {
        $this->selected = "selected";
    }
    
    public function disable() {
    		$this->disabled = "disabled";
    }
    
    public function hide() {
    		$this->hidden = "hidden";
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<option";
        $html .= $this->renderIdClass();
        if ($this->value != "") {
            $html .= " value=\"".$this->value."\"";
        }
        if ($this->selected != "") {
            $html .= " ".$this->selected;
        }
        if ($this->disabled != "") {
            $html .= " ".$this->disabled;
        }
        if ($this->hidden != "") {
            $html .= " ".$this->hidden;
        }
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</option>\n";
        return $html;
    }
}

class DOMspan extends DOMobject {

    //Constructor is specified for clarity, despite being the same as the parent's
    public function __construct($id = "") {
        parent::__construct($id);
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<span";
        $html .= $this->renderIdClass();
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</span>\n";
        return $html;
    }
    
}

//textarea class
class DOMtextarea extends DOMobject {
    
    //Constructor is specified for clarity, despite being the same as the parent's
    public function __construct($id = "") {
        parent::__construct($id);
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<textarea";
        $html .= $this->renderIdClass();
        $html .= ">";
        $html .= $this->renderChildren("");
        $html .= "</textarea>\n";
        return $html;
    }
    
}

//button class
class DOMbutton extends DOMobject {

		private $value = "";
    
    //Allow user to save inner text from constructor
    public function __construct($id = "", $innerText = "") {
        parent::__construct($id);
        if ($innerText != "") {
        		$this->children[] = $innerText;
        }
    }
    
    public function setValue($value) {
    		$this->value = $value;
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<button";
        $html .= $this->renderIdClass();
        if ($this->value != "") {
            $html .= " value=\"".$this->value."\"";
        }
        $html .= ">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</button>\n";
        return $html;
    }
    
}

class DOMhtml extends DOMobject {
    
    public function __construct() {
        parent::__construct("");
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent."<!doctype html>\n".$indent;
        $html .= "<html>\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</html>\n";
        return $html;
    }
    
}

class DOMhead extends DOMobject {
    
    public function __construct() {
        parent::__construct("");
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<head>\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</head>\n";
        return $html;
    }
    
}

class DOMbody extends DOMobject {
    
    public function __construct() {
        parent::__construct("");
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<body>\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</body>\n";
        return $html;
    }
    
}

class DOMtitle extends DOMobject {
    
    public function __construct($title = "") {
        parent::__construct("");
        if ($title != "") {
        	$this->children[] = $title;
        }
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<title>\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</title>\n";
        return $html;
    }
    
}

class DOMmeta extends DOMobject {
    
    //Store appropriate fields
    //type can be charset, http-equiv, or name
    private $type = "";
    private $value = "";
    private $content = "";
    
    public function __construct($type = "", $value = "", $content = "") {
        parent::__construct("");
        $this->type = $type;
        $this->value = $value;
        $this->content = $content;
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<meta ".$this->type."=\"".$this->value."\"";
        if ($this->content != "") {
            $html .= " content=\"".$this->content."\"";
        }
        $html .= " >\n";
        return $html;
    }
    
}

class DOMstyle extends DOMobject {
    
    public function __construct() {
        parent::__construct("");
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<style type=\"text/css\">\n";
        $html .= $this->renderChildren($indent."\t");
        $html .= $indent."</style>\n";
        return $html;
    }
    
}

class DOMlink extends DOMobject {
    
    //Store appropriate fields
    private $rel = "";
    private $type = "";
    private $href = "";
    
    public function __construct($rel = "stylesheet", $type = "text/css", $href = "/css/style.css") {
        parent::__construct("");
        $href = $this->cssPath; //Delete for deployment
        $this->rel = $rel;
        $this->type = $type;
        $this->href = $href;
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<link rel=\"".$this->rel."\" type=\"".$this->type."\" href=\"".$this->href."\">\n";
        return $html;
    }
    
}

class DOMscript extends DOMobject {
    
    //Store source link
    private $src = "";
    
    public function __construct($src = "/js/main.js") {
        parent::__construct("");
        $src = $this->jsPath; //Delete for deployment
        $this->src = $src;
    }
    
    //Return string representation of the element with given indent
    public function render($indent) {
        $html = $indent;
        $html .= "<script src=\"".$this->src."\">\n";
        $html .= $indent."</script>\n";
        return $html;
    }
    
}

?>
