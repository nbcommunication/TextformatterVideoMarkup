<?php namespace ProcessWire;

/**
 * Tests for TextformatterVideoMarkup
 *
 * Run via: php index.php test TextformatterVideoMarkup
 * (or `php index.php test all`, or the WireTests admin page)
 *
 * Note: this site hooks TextformatterVideoMarkup::render() (see site/ready.php)
 * to rewrite rendered <iframe> markup into a <lazy-iframe data="base64..."> element
 * for lazy loading, so iframe assertions here unwrap that via extractIframeMarkup().
 *
 */
class WireTest_TextformatterVideoMarkup extends WireTest {

	const testYoutubeUrl = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
	const testYoutubeShortUrl = 'https://youtu.be/dQw4w9WgXcQ';
	const testYoutubeBogusUrl = 'https://www.youtube.com/watch?v=thisIsNotARealVideoId000';

	// Big Buck Bunny - a long-standing, stable Vimeo demo/test video
	const testVimeoUrl = 'https://vimeo.com/1084537';
	const testVimeoWwwUrl = 'https://www.vimeo.com/1084537';

	/**
	 * @var string
	 */
	protected $plainFieldName;

	/**
	 * @var string
	 */
	protected $htmlFieldName;

	/**
	 * Module config values as they were before this test ran, restored in finish()
	 *
	 * @var array
	 */
	protected $originalConfig = [];

	/**
	 * Config keys this test may temporarily change
	 *
	 * @var array
	 */
	protected $configKeys = [
		'yt_rel', 'yt_modestbranding', 'yt_noCookie',
		'emptyValue', 'markupTpl',
	];

	/**
	 * Only run if the YouTube oEmbed endpoint is actually reachable - this test
	 * exercises real oEmbed HTTP requests (cached permanently by the module once
	 * fetched) and isn't meaningful without outbound network access.
	 *
	 * @return bool
	 *
	 */
	public function allow() {
		$http = new WireHttp();
		$data = $http->getJSON('https://www.youtube.com/oembed?url=' . urlencode(self::testYoutubeUrl) . '&format=json');
		return is_array($data) && count($data) > 0;
	}

	protected function getModule() {
		return $this->wire()->modules->get('TextformatterVideoMarkup');
	}

	public function init() {
		$prefix = WireTests::fieldPrefix . 'videomarkup_';
		$this->plainFieldName = $prefix . 'text';
		$this->htmlFieldName = $prefix . 'html';
		$this->ensureFields();

		$mod = $this->getModule();
		foreach($this->configKeys as $key) {
			$this->originalConfig[$key] = $mod->$key;
		}
	}

	/**
	 * Ensure a plain-text and an HTML test textarea field exist on the test page
	 *
	 */
	protected function ensureFields() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();

		foreach([
			$this->plainFieldName => FieldtypeTextarea::contentTypeUnknown,
			$this->htmlFieldName => FieldtypeTextarea::contentTypeHTML,
		] as $name => $contentType) {

			$field = $fields->get($name);
			if(!$field) {
				$field = new Field();
				$field->name = $name;
				$field->type = $modules->get('FieldtypeTextarea');
				$field->label = "Test Textarea ($name)";
				$field->contentType = $contentType;
				$field->textformatters = [ 'TextformatterVideoMarkup' ];
				$field->save();
				$this->li("Created field: $field->name");
			}

			$fieldgroup = $page->template->fieldgroup;
			if(!$fieldgroup->hasField($field)) {
				$fieldgroup->add($field);
				$fieldgroup->save();
				$this->li("Added field to fieldgroup: $fieldgroup->name");
			}
		}
	}

	/**
	 * Extract the underlying <iframe>...</iframe> markup for assertions
	 *
	 * This site hooks TextformatterVideoMarkup::render() (see site/ready.php) to
	 * rewrite the rendered <iframe> into a <lazy-iframe data="base64..."> element
	 * for lazy loading. Unwrap that here so assertions work whether or not that
	 * site-specific hook is active.
	 *
	 * @param string $html
	 * @return string
	 *
	 */
	protected function extractIframeMarkup($html) {
		if(preg_match('/<lazy-iframe\s+data="([^"]+)"/', $html, $matches)) {
			return base64_decode($matches[1]);
		}
		return $html;
	}

	public function execute() {

		$mod = $this->getModule();
		$page = $this->getTestPage();
		$page->of(false);

		$plainField = $this->wire()->fields->get($this->plainFieldName);
		$htmlField = $this->wire()->fields->get($this->htmlFieldName);

		if(!$plainField || !$htmlField) $this->fail('Test fields are not available');

		// Reset to known defaults for deterministic assertions.
		// markupTpl is explicitly forced to '{html}' (rather than left empty) because
		// when $config->debug is true, an empty markupTpl causes the module to render
		// its admin debug data-table instead of the actual video markup.
		$mod->yt_rel = '0';
		$mod->yt_modestbranding = '1';
		$mod->yt_noCookie = '1';
		$mod->emptyValue = '';
		$mod->markupTpl = '{html}';

		// --- Basic matching/rendering (plain text mode) ---

		$value = self::testYoutubeUrl;
		$mod->formatValue($page, $plainField, $value);
		$iframe = $this->extractIframeMarkup($value);
		$this->check('formatValue() renders YouTube watch URL to an iframe', true, strpos($iframe, '<iframe') !== false);
		$this->check('formatValue() YouTube render uses nocookie domain (yt_noCookie=1)', true, strpos($iframe, 'youtube-nocookie.com') !== false);

		$value = self::testYoutubeShortUrl;
		$mod->formatValue($page, $plainField, $value);
		$iframe = $this->extractIframeMarkup($value);
		$this->check('formatValue() renders youtu.be short URL to an iframe', true, strpos($iframe, '<iframe') !== false);

		$value = self::testVimeoUrl;
		$mod->formatValue($page, $plainField, $value);
		$iframe = $this->extractIframeMarkup($value);
		$this->check('formatValue() renders Vimeo URL to an iframe', true, strpos($iframe, '<iframe') !== false);

		// --- HTML mode: URL must be alone in its own paragraph to be matched ---

		$value = '<p>' . self::testYoutubeUrl . '</p>';
		$mod->formatValue($page, $htmlField, $value);
		$iframe = $this->extractIframeMarkup($value);
		$this->check('formatValue() (HTML mode) renders paragraph-wrapped YouTube URL', true, strpos($iframe, '<iframe') !== false);

		$value = '<p>Some text and ' . self::testYoutubeUrl . ' inline</p>';
		$mod->formatValue($page, $htmlField, $value);
		$this->check(
			'formatValue() (HTML mode) leaves inline (non-own-paragraph) URL alone',
			true,
			strpos($value, '<iframe') === false && strpos($value, '<lazy-iframe') === false
		);

		// --- www.vimeo.com URLs are recognised (optional "www." prefix) ---

		$value = self::testVimeoWwwUrl;
		$mod->formatValue($page, $plainField, $value);
		$iframe = $this->extractIframeMarkup($value);
		$this->check('formatValue() renders www.vimeo.com URLs', true, strpos($iframe, '<iframe') !== false);

		// --- Configured emptyValue is used when the oEmbed lookup fails ---

		$mod->emptyValue = 'NO VIDEO: {url}';
		$value = self::testYoutubeBogusUrl;
		$mod->formatValue($page, $plainField, $value);
		$this->check('formatValue() uses configured emptyValue when oEmbed lookup fails', true, strpos($value, 'NO VIDEO:') !== false);
		$mod->emptyValue = '';

		// --- No undefined array key warning when no query params are present ---
		// (lite facade markup interpolates $data['params'], which must always be set)

		$mod->yt_rel = '';
		$mod->yt_modestbranding = '';
		$mod->yt_noCookie = '';

		$warnings = [];
		set_error_handler(function($errno, $errstr) use (&$warnings) {
			$warnings[] = $errstr;
			return true;
		}, E_WARNING);

		try {
			$value = self::testYoutubeUrl;
			$mod->formatValue($page, $plainField, $value);
		} finally {
			restore_error_handler();
		}

		$undefinedKeyWarnings = array_filter($warnings, function($w) {
			return stripos($w, 'undefined array key') !== false;
		});

		$this->check(
			'formatValue() does not raise undefined array key warnings when no query params are present',
			0,
			count($undefinedKeyWarnings)
		);

		// Restore known-good config before finish() runs
		$mod->yt_rel = '0';
		$mod->yt_modestbranding = '1';
		$mod->yt_noCookie = '1';
	}

	public function finish() {
		$mod = $this->getModule();
		foreach($this->originalConfig as $key => $value) {
			$mod->$key = $value;
		}
	}
}
