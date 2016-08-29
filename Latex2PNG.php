<?php
class Latex2PNG {
	private $latex = '';
	private $static_images = false;
	private $syntax_err = array();
	private $CHKTEX_PATH = '/usr/bin/chktex';
	private $CACHE_DIR = './cache';

	/*
	* Purpose: if variable latex is set, initialize variables
	*
	* @param DOMDocument xmlNode: xml node containing all object data
	*
	* @return Latex2PNG object
	*/
	public function __construct($latex = '', $static_images = false) {
		if (!empty($latex)) {
			$this->latex = $latex;
			$this->static_images = $static_images;
			$this->processImage();
		}// end if
	}// end constructor

	public function __set($property, $value) {
		if (property_exists($this, $property))
			$this->$property = $value;

		return $this;
	}// end magic setter

	public function __get($property) {
		if (property_exists($this, $property))
			return $this->$property;
	}// end magic getter

	/*
	* Purpose: add problem to database, or update problem in database
	*
	* @return null
	*/
	public function processImage() {
		if (file_exists($this->CHKTEX_PATH))
			$this->cleanLatex();

		// transform latex code into image
		$this->processLatex();

		return $this;
	}// end processDb

	/*
	* Purpose: remove malicious and unnecessary characters from latex
	*
	* @return null
	*
	*/
	private function cleanLatex() {
		// remove script tags
		$this->latex = preg_replace("/(\s+?)<script.*?>.*?<\/script>(\s+)?/", '', $this->latex);

		// remove latex tags to prevent possible XSS attacks
		$this->latex = preg_replace("/\\\\immediate/", '', $this->latex);
		$this->latex = preg_replace("/\\\\write18/", '', $this->latex);
		$this->latex = preg_replace("/\\\\write/", '', $this->latex);

		// remove multiple space characters
		$this->latex = preg_replace('/[ \t]+/', " ", trim($this->latex));

		// remove illegal unicode characters
		$this->latex = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $this->latex);
	}// end cleanLatex

	/*
	* Purpose: check latex syntax
	*
	* @return boolean: true for valid syntax, false otherwise
	*/
	public function syntaxCheck() {
		# use "-n [number]" switch to ignore chktex errors
		$command = $this->CHKTEX_PATH . " -qv0 temp.tex";

		foreach (array($this->q, $this->s, $this->c) as $latex) {
			// 'mask' asymptote code to avoid false positives
			// remove newlines to avoid input cutoff
			$this->latex = str_replace("\r", '', $this->latex);
			$this->latex = str_replace("\n", '', $this->latex);

			// find all asymptote code sections
			preg_match_all("/(\\\\begin\\{asy\\}.*?\\\\end\\{asy\\})/", $this->latex, $matches);

			// replace asymptote code with nothing
			for ($i = 0; $i < count($matches[0]); $i++) {
				$position = strpos($this->latex, $matches[0][$i]);
				$this->latex = substr_replace($this->latex, '', $position, strlen($matches[0][$i]));
			}// end for

			// create temporary LaTeX file
			$fp = fopen('temp.tex', 'w+');
			fputs($fp, $this->latex);
			fclose($fp);

			exec($command, $this->syntax_err);
			unlink('temp.tex');

			if (!empty($this->syntax_err)) {
				for ($i = 0; $i < count($this->syntax_err); $i++) {
					// err_entries indices:
					// 0: filename (temp.tex)
					// 1: line number
					// 2: column number
					// 3: chktex error number
					// 4: error message
					$err_entries = explode(':', $this->syntax_err[$i]);

					$this->syntax_err[$i] = "{$latexSection[$j]} error {$err_entries[3]} on line {$err_entries[1]}: {$err_entries[4]}";

					if (count($matches[0]))
						$this->syntax_err[$i] .= " [Asymptote Code Ignored]";
				}// end foreach
			}// end if

			$j++;
		}// end foreach

		return true;
	}// end syntaxCheck

	/*
	* Purpose: render latex tags to images
	*
	* @return array: problem object
	*/
	private function processLatex() {
		if ($this->static_images) {
			require_once('Render2.php');
			$render = new Render2();

		} else {
			require_once('Render.php');
			$render = new Render();
		}// end if

		require_once('Asymptote.php');

		$path = $this->CACHE_DIR;

		// render TeX to PNG
		$this->latex = $render->transform($this->latex, $path);

		unset($render);

		return $this;
	}// end processLatex
}// end Latex2PNG class
