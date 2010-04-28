<?php
	
	require_once(TOOLKIT . '/class.htmldocument.php');
	require_once(TOOLKIT . '/class.view.php');
	require_once(TOOLKIT . '/class.urlwriter.php');
	
	class DevKit extends View {
		protected $document;
		protected $data;
		protected $url;
		
		public function __construct(View $view) {
			parent::__construct();
			
			$this->document = new HTMLDocument();
			
			$this->view = $view;
			$this->data = (object)array();
			$this->url = new URLWriter(URL . getCurrentPage(), $_GET);
			
			// Remove symphony parameters:
			unset($this->url->parameters()->{'symphony-page'});
			unset($this->url->parameters()->{'symphony-renderer'});
		}
		
		public function __isset($name) {
			return isset($this->data->$name);
		}
		
		public function __get($name) {
			if ($name == 'title' and !isset($this->title)) {
				$this->title = __('Untitled');
			}
			
			return $this->data->$name;
		}
		
		public function __set($name, $value) {
			$this->data->$name = $value;
		}
		
		public function templatePathname() {
			return $this->view->templatePathname();
		}
		
		protected function createScriptElement($path) {
			$element = $this->document->createElement('script');
			$element->setAttribute('type', 'text/javascript');
			$element->setAttribute('src', $path);

			// Creating an empty text node forces <script></script>
			$element->appendChild($this->document->createTextNode(''));

			return $element;
		}

		protected function createStylesheetElement($path, $type = 'screen') {
			$element = $this->document->createElement('link');
			$element->setAttribute('type', 'text/css');
			$element->setAttribute('rel', 'stylesheet');
			$element->setAttribute('media', $type);
			$element->setAttribute('href', $path);
			
			return $element;
		}
		
		public function render(Register &$parameters, XMLDocument &$document) {
			Widget::init($this->document);
			
			$this->appendHead($this->document->documentElement);
			$this->appendBody($this->document->documentElement);
			
			return $this->document->saveHTML();
		}
		
		protected function appendHead(DOMElement $wrapper) {
			$head = $this->document->createElement('head');
			
			$title = $this->document->createElement('title');
			$title->appendChild($this->document->createTextNode(
				__('Symphony') . ' '
			));
			$title->appendChild(
				$this->document->createEntityReference('ndash')
			);
			$title->appendChild($this->document->createTextNode(
				' ' . $this->view->title
			));
			$title->appendChild(
				$this->document->createEntityReference('ndash')
			);
			$title->appendChild($this->document->createTextNode(
				' ' . $this->title
			));
			$head->appendChild($title);
			
			$this->appendIncludes($head);
			$wrapper->appendChild($head);
			
			return $head;
		}
		
		protected function appendIncludes(DOMElement $wrapper) {
			$wrapper->appendChild(
				$this->createStylesheetElement(ADMIN_URL . '/assets/css/devkit.css')
			);
		}
		
		protected function appendBody(DOMElement $wrapper) {
			$body = $this->document->createElement('body');
			
			$this->appendContent($body);
			$this->appendSidebar($body);
			
			$wrapper->appendChild($body);
			
			return $body;
		}
		
		protected function appendContent(DOMElement $wrapper) {
			$container = $this->document->createElement('div');
			$container->setAttribute('id', 'content');
			
			$wrapper->appendChild($container);
			
			return $container;
		}
		
		protected function appendSidebar(DOMElement $wrapper) {
			$container = $this->document->createElement('div');
			$container->setAttribute('id', 'sidebar');
			
			$this->appendHeader($container);
			$this->appendMenu($container);
			
			$jump = $this->document->createElement('ul');
			$jump->setAttribute('id', 'jump');
			$this->appendJump($jump);
			$container->appendChild($jump);
			
			$wrapper->appendChild($container);
			
			return $container;
		}
		
		protected function appendHeader(DOMElement $wrapper) {
			$header = $this->document->createElement('h1');
			
			$link = Widget::Anchor(
				$this->view->title, (string)$this->url
			);
			$header->appendChild($link);
			
			$link = Widget::Anchor(
				__('Edit'), ADMIN_URL . '/blueprints/views/edit/' . $this->view->handle . '/'
			);
			$link->setAttribute('class', 'edit');
			
			$header->appendChild($link);
			$wrapper->appendChild($header);
			
			return $header;
		}
		
		protected function appendMenu(DOMElement $wrapper) {
			$container = $this->document->createElement('ul');
			$container->setAttribute('id', 'menu');
			
			$root = $this->document->createElement('navigation');
			
			// Add edit link:
			/*
			$item = $this->document->createElement('item');
			$item->appendChild();
			$root->appendChild($item);
			*/
			
			####
			# Delegate: DevKiAppendtMenuItem
			# Description: Allow navigation XML to be manipulated before it is rendered.
			# Global: Yes
			#$this->_page->ExtensionManager->notifyMembers(
			ExtensionManager::instance()->notifyMembers(
				'AppendDevKitMenuItem', '/frontend/',
				array(
					'wrapper'	=> $root
				)
			);
			
			if ($root->hasChildNodes()) {
				foreach ($root->childNodes as $node) {
					if ($node->getAttribute('active') == 'yes') {
						$item = $this->document->createElement('li', $node->getAttribute('name'));
					}
					
					else {
						$handle = $node->getAttribute('handle');
						
						$url = clone $this->url;
						$url->parameters()->$handle = null;
						
						$item = $this->document->createElement('li');
						$item->appendChild(Widget::Anchor(
							$node->getAttribute('name'),
							'?' . (string)$url
						));
					}
					
					$container->appendChild($item);
				}
			}
			
			$wrapper->appendChild($container);
			
			return $container;
		}
		
		protected function appendJump(DOMElement $wrapper) {
			
		}
		
		protected function appendJumpItem(DOMElement $wrapper, $name, $link, $active = false) {
			$item = $this->document->createElement('li');
			$anchor = $this->document->createElement('a');
			$anchor->setAttribute('href', $link);
			$anchor->setAttribute('class', 'inactive');
			$anchor->appendChild(
				$this->document->createTextNode($name)
			);
			
			if ($active == true) {
				$anchor->setAttribute('class', 'active');
			}
			
			$item->appendChild($anchor);
			$wrapper->appendChild($item);
			
			return $item;
		}
		
		
		
		
		/*
		protected $_query_string = '';
		protected $_page = null;
		protected $_pagedata = null;
		protected $_xml = null;
		protected $_param = array();
		protected $_output = '';
		
		protected function buildIncludes() {
			$this->addHeaderToPage('Content-Type', 'text/html; charset=UTF-8');
			
			$this->Html->setElementStyle('html');
			$this->Html->setDTD('<!DOCTYPE html>');
			$this->Html->setAttribute('lang', __LANG__);
			$this->addElementToHead(new XMLElement(
				'meta', null,
				array(
					'http-equiv'	=> 'Content-Type',
					'content'		=> 'text/html; charset=UTF-8'
				)
			));
			$this->addStylesheetToHead(ADMIN_URL . '/assets/css/devkit.css', 'screen');
		}
		
		protected function buildHeader($wrapper) {
			$this->setTitle(__(
				'%1$s &ndash; %2$s &ndash; %3$s',
				array(
					__('Symphony'),
					__($this->_title),
					$this->_pagedata['title']
				)
			));
			
			$h1 = new XMLElement('h1');
			$h1->appendChild(Widget::Anchor(
				$this->_pagedata['title'], ($this->_query_string ? '?' . trim(html_entity_decode($this->_query_string), '&') : '.')
			));
			
			$wrapper->appendChild($h1);
		}
		
		protected function buildNavigation($wrapper) {
			$xml = new DOMDocument();
			$xml->preserveWhiteSpace = false;
			$xml->formatOutput = true;
			$root = $xml->createElement('navigation');
			$xml->appendChild($root);
			
			$first = $root->firstChild;
			$xpath = new DOMXPath($xml);
			$list = new XMLElement('ul');
			$list->setAttribute('id', 'navigation');
			
			// Add edit link:
			$item = new XMLElement('li');
			$item->appendChild(Widget::Anchor(
				__('Edit'), ADMIN_URL . '/blueprints/pages/edit/' . $this->_pagedata['id'] . '/'
			));
			$list->appendChild($item);
			
			// Translate navigaton names:
			if ($root->hasChildNodes()) {
				foreach ($root->childNodes as $item) if ($item->tagName == 'item') {
					$item->setAttribute('name', __($item->getAttribute('name')));
				}
			}
			
			####
			# Delegate: ManipulateDevKitNavigation
			# Description: Allow navigation XML to be manipulated before it is rendered.
			# Global: Yes
			#$this->_page->ExtensionManager->notifyMembers(
			ExtensionManager::instance()->notifyMembers(
				'ManipulateDevKitNavigation', '/frontend/',
				array(
					'xml'	=> $xml
				)
			);
			
			if ($root->hasChildNodes()) {
				foreach ($root->childNodes as $node) {
					if ($node->getAttribute('active') == 'yes') {
						$item = new XMLElement('li', $node->getAttribute('name'));
						
					} else {
						$item = new XMLElement('li');
						$item->appendChild(Widget::Anchor(
							$node->getAttribute('name'),
							'?' . $node->getAttribute('handle') . $this->_query_string
						));
					}
					
					$list->appendChild($item);
				}
			}
			
			$wrapper->appendChild($list);
		}
		
		protected function buildJump($wrapper) {
			
		}
		
		protected function buildContent($wrapper) {
			
		}
		
		protected function buildJumpItem($name, $link, $active = false) {
			$item = new XMLElement('li');
			$anchor = Widget::Anchor($name,  $link);
			$anchor->setAttribute('class', 'inactive');
			
			if ($active == true) {
				$anchor->setAttribute('class', 'active');
			}
			
			$item->appendChild($anchor);
			
			return $item;
		}
		
		public function prepare($page, $pagedata, $xml, $param, $output) {
			$this->_page = $page;
			$this->_pagedata = $pagedata;
			$this->_xml = $xml;
			$this->_param = $param;
			$this->_output = $output;
			
			if (is_null($this->_title)) {
				$this->_title = __('Utility');
			}
		}
		
		public function build() {
			$this->buildIncludes();
			
			$header = new XMLElement('div');
			$header->setAttribute('id', 'header');
			$jump = new XMLElement('div');
			$jump->setAttribute('id', 'jump');
			$content = new XMLElement('div');
			$content->setAttribute('id', 'content');
			
			$this->buildHeader($header);
			$this->buildNavigation($header);
			
			$this->buildJump($jump);
			$header->appendChild($jump);
			
			$this->Body->appendChild($header);
			
			$this->buildContent($content);
			$this->Body->appendChild($content);
			
			return parent::generate();
		}
		*/
	}
	
?>
