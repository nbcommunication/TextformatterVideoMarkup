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
	const testYoutubeShortsUrl = 'https://www.youtube.com/shorts/dQw4w9WgXcQ';
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
		'yt_rel', 'yt_modestbranding', 'yt_noCookie', 'vm_dnt',
		'emptyValue', 'markupTpl', 'oembedCacheExpiry',
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

		// --- Vimeo upload_date is also available as an ISO 8601 date and timestamp ---

		$mod->markupTpl = '{upload_date}|{published_at}|{published_timestamp}';
		$value = self::testVimeoUrl;
		$mod->formatValue($page, $plainField, $value);
		$parts = explode('|', $value);
		$this->check('formatValue() adds Vimeo published_at (ISO 8601)', '2008-05-29T05:08:02+00:00', $parts[1] ?? '');
		$this->check('formatValue() adds Vimeo published_timestamp', strtotime('2008-05-29 05:08:02 UTC'), (int) ($parts[2] ?? 0));
		$mod->markupTpl = '{html}';

		// Helpers for reaching protected module methods and the cache table
		$cache = $this->wire()->cache;
		$call = function(\Closure $fn) use ($mod) {
			return \Closure::bind($fn, $mod, get_class($mod))();
		};
		$cacheName = function($provider, $url) use ($call) {
			return $call(function() use ($provider, $url) {
				$url = $provider === 'yt' ? $this->applyYoutubeOptions($url) : $this->applyVimeoOptions($url);
				return $this->getOembedCacheName($this->getOembedEndpoint($provider, $url));
			});
		};
		$cacheExpires = function($name) use ($mod) {
			$query = $this->wire()->database->prepare('SELECT expires FROM caches WHERE name=:name');
			$query->bindValue(':name', wireClassName($mod, false) . "__$name");
			$query->execute();
			$expires = $query->fetchColumn();
			$query->closeCursor();
			return $expires === false ? null : $expires;
		};
		$expiresIn = function($name) use ($cacheExpires) {
			$expires = $cacheExpires($name);
			return $expires ? strtotime($expires) - time() : 0;
		};

		// --- Options are added to the iframe src only (not e.g. its title) ---

		$mod->vm_dnt = '1';
		$value = self::testVimeoUrl;
		$mod->formatValue($page, $plainField, $value);
		$iframe = $this->extractIframeMarkup($value);
		preg_match('/\ssrc="([^"]*)"/', $iframe, $src);
		preg_match('/\stitle="([^"]*)"/', $iframe, $title);
		$this->check('formatValue() adds options to the iframe src', true, strpos($src[1] ?? '', 'dnt=1') !== false);
		$this->check('formatValue() does not add options to the iframe title', 'Big Buck Bunny', $title[1] ?? '');
		$mod->vm_dnt = '';

		// --- youtu.be URLs use the nocookie domain, even with no other options set ---

		$mod->yt_noCookie = '1';
		$value = self::testYoutubeShortUrl;
		$mod->formatValue($page, $plainField, $value);
		$iframe = $this->extractIframeMarkup($value);
		$this->check('formatValue() youtu.be render uses nocookie domain with no other options', true, strpos($iframe, 'youtube-nocookie.com') !== false);
		$mod->yt_noCookie = '';

		// --- YouTube Shorts URLs ---

		$value = self::testYoutubeShortsUrl;
		$mod->formatValue($page, $plainField, $value);
		$iframe = $this->extractIframeMarkup($value);
		$this->check('formatValue() renders YouTube shorts URL to an iframe', true, strpos($iframe, '<iframe') !== false);

		// --- emptyValue {link} renders a link (in a paragraph in HTML mode) ---

		$mod->emptyValue = '{link}';
		$bogus = self::testYoutubeBogusUrl;
		$value = "<p>$bogus</p>";
		$mod->formatValue($page, $htmlField, $value);
		$this->check(
			'formatValue() (HTML mode) renders emptyValue {link} as a link in a paragraph',
			"<p><a href=$bogus target=_blank rel=noopener>$bogus</a></p>",
			$value
		);
		$mod->emptyValue = '';

		// --- Failed oEmbed requests are cached for an hour ---

		$name = $cacheName('yt', self::testYoutubeBogusUrl);
		$cache->deleteFor($mod, $name);
		$mod->emptyValue = 'NO VIDEO';
		$value = self::testYoutubeBogusUrl;
		$mod->formatValue($page, $plainField, $value);
		$cached = $cache->getFor($mod, $name);
		$this->check('formatValue() caches a failed oEmbed request as an error', true, is_array($cached) && !empty($cached['error']));
		$seconds = $expiresIn($name);
		$this->check('formatValue() caches a failed oEmbed request for an hour', true, $seconds > 3500 && $seconds <= 3600);
		$value = self::testYoutubeBogusUrl;
		$mod->formatValue($page, $plainField, $value);
		$this->check('formatValue() renders emptyValue for a cached failure', 'NO VIDEO', $value);
		$mod->emptyValue = '';

		// --- oembedCacheExpiry sets the oEmbed cache expiry ---

		$name = $cacheName('vm', self::testVimeoUrl);

		$mod->oembedCacheExpiry = WireCache::expireHourly;
		$cache->deleteFor($mod, $name);
		$value = self::testVimeoUrl;
		$mod->formatValue($page, $plainField, $value);
		$seconds = $expiresIn($name);
		$this->check('formatValue() caches oEmbed data for oembedCacheExpiry (hourly)', true, $seconds > 3500 && $seconds <= 3600);

		$mod->oembedCacheExpiry = WireCache::expireNever;
		$cache->deleteFor($mod, $name);
		$value = self::testVimeoUrl;
		$mod->formatValue($page, $plainField, $value);
		$this->check('formatValue() caches oEmbed data permanently by default', WireCache::expireNever, $cacheExpires($name));

		// --- oEmbed text values are entity encoded, the embed html is not ---

		$encoded = $call(function() {
			return $this->encodeData([
				'title' => 'Tom & "Jerry" <3',
				'html' => '<iframe src="https://example.com/?a=1&b=2"></iframe>',
				'width' => 640,
			], ['html']);
		});
		$this->check('encodeData() entity encodes string values', 'Tom &amp; &quot;Jerry&quot; &lt;3', $encoded['title']);
		$this->check('encodeData() leaves skipped keys as they are', '<iframe src="https://example.com/?a=1&b=2"></iframe>', $encoded['html']);
		$this->check('encodeData() leaves non-string values as they are', 640, $encoded['width']);

		// --- YouTube Data API extends the oEmbed data (only if a key is configured) ---

		if($mod->youtubeApiKey) {
			$mod->markupTpl = '{duration}|{channel_id}|{html}';
			$value = self::testYoutubeUrl;
			$mod->formatValue($page, $plainField, $value);
			$parts = explode('|', $value, 3);
			$this->check('formatValue() adds YouTube Data API duration (seconds)', true, (int) $parts[0] > 0);
			$this->check('formatValue() adds YouTube Data API channel_id', true, isset($parts[1]) && strpos($parts[1], 'UC') === 0);
			$this->check('formatValue() does not leak the API key into the embed', false, strpos($value, $mod->youtubeApiKey) !== false);

			// Normalised date and duration keys are consistent
			$mod->markupTpl = '{upload_date}|{published_at}|{published_timestamp}|{duration}|{duration_formatted}';
			$value = self::testYoutubeUrl;
			$mod->formatValue($page, $plainField, $value);
			$parts = array_pad(explode('|', $value), 5, '');
			$timestamp = (int) $parts[2];
			$this->check('formatValue() YouTube upload_date matches published_timestamp', $timestamp, (int) strtotime("$parts[0] UTC"));
			$this->check('formatValue() YouTube published_at matches published_timestamp', $timestamp, (int) strtotime($parts[1]));
			$seconds = (int) $parts[3];
			$this->check(
				'formatValue() YouTube duration_formatted matches duration',
				$seconds >= 3600 ?
					sprintf('%d:%02d:%02d', floor($seconds / 3600), floor(($seconds % 3600) / 60), $seconds % 60) :
					sprintf('%d:%02d', floor($seconds / 60), $seconds % 60),
				$parts[4]
			);

			// An invalid key falls back to the oEmbed data, and the failure is cached
			$apiCacheName = TextformatterVideoMarkup::youtubeApiCachePrefix . 'dQw4w9WgXcQ';
			$apiKey = $mod->youtubeApiKey;
			$mod->youtubeApiKey = 'invalid-wiretest-key';
			$cache->deleteFor($mod, $apiCacheName);
			try {
				$mod->markupTpl = '{channel_id}|{title}';
				$value = self::testYoutubeUrl;
				$mod->formatValue($page, $plainField, $value);
				$parts = explode('|', $value, 2);
				$this->check('formatValue() with an invalid API key leaves API placeholders unreplaced', '{channel_id}', $parts[0]);
				$this->check('formatValue() with an invalid API key still renders oEmbed data', true, isset($parts[1]) && $parts[1] !== '' && $parts[1] !== '{title}');
				$cached = $cache->getFor($mod, $apiCacheName);
				$this->check('formatValue() caches a failed YouTube Data API request as an error', true, is_array($cached) && !empty($cached['error']));
			} finally {
				// Restore the key and clear the cached failure, so the real data is fetched again
				$mod->youtubeApiKey = $apiKey;
				$cache->deleteFor($mod, $apiCacheName);
			}

			$mod->markupTpl = '{html}';
		} else {
			$this->li('Skipped YouTube Data API tests (no youtubeApiKey configured)');
		}

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
