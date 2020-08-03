<?php namespace ProcessWire;

/**
 * Textformatter Video Markup Configuration
 *
 */

class TextformatterVideoMarkupConfig extends ModuleConfig {

	/**
	 * Returns default values for module variables
	 *
	 * @return array
	 *
	 */
	public function getDefaults() {
		return [
			'emptyValue' => '',
			'maxWidth' => 1280,
			'maxHeight' => 720,
		];
	}

	/**
	 * Returns inputs for module configuration
	 *
	 * @return InputfieldWrapper
	 *
	 */
	public function getInputfields() {

		$input = $this->wire('input');
		$modules = $this->wire('modules');
		$inputfields = parent::getInputfields();

		$tf = $modules->get(str_replace('Config', '', $this->className));

		if($input->post('clearCache')) {
			$this->wire('cache')->deleteFor($tf);
			$tf->message($this->_('Cache cleared'));
			$this->wire('session')->redirect($input->url(true));
		}

		// Load ACE editor from Hanna Code
		$aceData = null;
		if($modules->isInstalled('ProcessHannaCode')) {

			$hc = $modules->get('ProcessHannaCode');
			$modules->loadModuleFileAssets($hc);
			$this->wire('config')->scripts->add($this->wire('config')->urls($hc) . 'ace-' . $hc::aceVersion . '/src-min/ace.js');

			$aceData = [
				'aceTheme' => $hc::defaultAceTheme,
				'aceKeybinding' => $hc::defaultAceKeybinding,
				'aceHeight' => $hc::defaultAceHeight,
				'aceBehaviors' => $hc::defaultAceBehaviors
			];
			foreach($aceData as $key => $default) {
				$value = $this->wire('session')->get($hc->className() . '_' . $key);
				if(!$value) $value = $hc->get($key);
				if(!$value) $value = $default;
				$aceData[$key] = $value;
			}
		}

		// Template
		$inputfields->add([
			'type' => 'textarea',
			'name' => 'markupTpl',
			'id' => 'hc_code',
			'label' => $this->_('Markup'),
			'notes' => sprintf($this->_('Please refer to %s for details on how to use this field.'), 'README.md'),
			'icon' => 'code',
			'rows' => 10,
			'collapsed' => 2,
			'attr' => (is_array($aceData) ? [
				'data-theme' => $aceData['aceTheme'],
				'data-keybinding' => $aceData['aceKeybinding'],
				'data-height' => $aceData['aceHeight'],
				'data-behaviors' => (int) $aceData['aceBehaviors'],
			] : []),
		]);

		$options = $modules->get('InputfieldFieldset');
		$options->label = $this->_('Video Options');
		$options->icon = 'cog';

		// Max Width
		$options->add([
			'type' => 'integer',
			'name' => 'maxWidth',
			'label' => $this->_('Max Width'),
			'icon' => 'arrows-h',
			'columnWidth' => 50,
		]);

		// Max Height
		$options->add([
			'type' => 'integer',
			'name' => 'maxHeight',
			'label' => $this->_('Max Height'),
			'icon' => 'arrows-v',
			'columnWidth' => 50,
		]);

		// Empty Value
		$options->add([
			'type' => 'text',
			'name' => 'emptyValue',
			'label' => $this->_('Empty Value'),
			'description' => $this->_('This is the value that will be rendered if no response is received from the oEmbed endpoint.'),
			'icon' => 'exclamation-circle',
			'collapsed' => 2,
		]);

		$inputfields->add($options);

		// Youtube Options
		$info = 'https://developers.google.com/youtube/player_parameters#Parameters';
		$yt = $modules->get('InputfieldFieldset');
		$yt->label = $this->_('YouTube Options');
		$yt->icon = 'youtube';
		$yt->notes = sprintf($this->_('More information: %s'), "[$info]($info)");
		$yt->collapsed = 1;

		$iso = 'http://www.loc.gov/standards/iso639-2/php/code_list.php';
		$lang = $this->_('If multi-language support is installed, this will default to the `name` of the current user language.');

		foreach([
			'noCookie' => [
				'label' => $this->_('Enable privacy-enhanced mode?'),
				'description' => $this->_("When enabled, YouTube won't store information about visitors on your website unless they play the video."),
			],
			'autoplay' => [
				'label' => $this->_('Autoplay'),
				'description' => $this->_('Specifies whether the initial video will automatically start to play when the player loads.'),
			],
			'cc_lang_pref' => [
				'type' => 'text',
				'label' => $this->_('Closed Captions Language Preference'),
				'description' => sprintf($this->_('Specifies the default language that the player will use to display captions. Set the value to an %s two-letter language code.'), "[ISO 639-1]($iso)"),
				'notes' => implode("\n", [
					$this->_('If you use this parameter and also set the `cc_load_policy` parameter to 1, then the player will show captions in the specified language when the player loads. If you do not also set the `cc_load_policy` parameter, then captions will not display by default, but will display in the specified language if the user opts to turn captions on.'),
					$lang
				]),
			],
			'cc_load_policy' => [
				'label' => $this->_('Closed Captions Load Policy'),
				'description' => $this->_("Enabling this causes closed captions to be shown by default, even if the user has turned captions off. The default behavior is based on user preference."),
			],
			'color' => [
				'label' => $this->_('Color'),
				'description' => sprintf(
					$this->_("Specifies the color that will be used in the player's video progress bar to highlight the amount of the video that the viewer has already seen. By default, the player uses the color red in the video progress bar. See the %s for more information about color options."),
					sprintf('[%s](http://youtube-eng.blogspot.com/2011/08/coming-soon-dark-player-for-embeds_5.html)', $this->_('YouTube API blog'))
				),
				'notes' => $this->_('Setting the color parameter to white will disable the `modestbranding` option.'),
				'options' => [
					'' => '',
					'red' => $this->_('Red'),
					'white' => $this->_('White'),
				],
			],
			'controls' => [
				'label' => $this->_('Controls'),
				'description' => $this->_('Indicates whether the video player controls are displayed.'),
				'notes' => implode("\n", [
					$this->_("Off - Player controls do not display in the player."),
					$this->_("On (default) - Player controls display in the player."),
				]),
			],
			'disablekb' => [
				'label' => $this->_('Disable Keyboard Controls'),
				'description' => $this->_('Disabling causes the player to not respond to keyboard controls.'),
			],
			/*'enablejsapi' => [
				'label' => $this->_('Enable JS API'),
				'description' => $this->_('Enables the player to be controlled via IFrame API calls.'),
				'notes' => sprintf(
					$this->_('For more information on the IFrame API and how to use it, see the %s.'),
					sprintf('[%s](https://developers.google.com/youtube/iframe_api_reference)', $this->_('IFrame API documentation'))
				),
			],*/
			'fs' => [
				'label' => $this->_('Fullscreen'),
				'description' => $this->_('Display the fullscreen button in the player.'),
			],
			'hl' => [
				'type' => 'text',
				'label' => $this->_('Interface Language'),
				'description' => sprintf($this->_("Sets the player's interface language. The value is an %s two-letter language code or a fully specified locale. For example, fr and fr-ca are both valid values. Other language input codes, such as IETF language tags (BCP 47) might also be handled properly."), "[ISO 639-1]($iso)"),
				'notes' => $this->_("The interface language is used for tooltips in the player and also affects the default caption track. Note that YouTube might select a different caption track language for a particular user based on the user's individual language preferences and the availability of caption tracks."),
				'notes' => $lang,
			],
			'iv_load_policy' => [
				'label' => $this->_('Annotations Behaviour'),
				'options' => [
					'' => '',
					'1' => $this->_('Show annotations'),
					'3' => $this->_('Do not show annotations'),
				],
			],
			/*'loop' => [
				'label' => $this->_('Loop'),
				'description' => $this->_('Causes the player to play the initial video again and again.'),
			],*/ // Doesn't appear to work
			'modestbranding' => [
				'label' => $this->_('Modest Branding'),
				'description' => $this->_('Lets you use a YouTube player that does not show a YouTube logo. Enable to prevent the YouTube logo from displaying in the control bar.'),
				'notes' => $this->_("When enabled, a small YouTube text label will still display in the upper-right corner of a paused video when the user's mouse pointer hovers over the player"),
			],
			/*'origin' => [
				'type' => 'text',
				'label' => $this->_('Origin Domain'),
				'description' => $this->_('This parameter provides an extra security measure for the IFrame API and is only supported for IFrame embeds.'),
				'notes' => $this->_('If you are using the IFrame API, which means you are enabling the `enablejsapi` parameter, you should always specify your domain as the origin parameter value.'),
			],*/
			'playsinline' => [
				'label' => $this->_('Play inline'),
				'description' => $this->_('Controls whether videos play inline or fullscreen in an HTML5 player on iOS.'),
			],
			'rel' => [
				'label' => $this->_('Related Videos'),
				'description' => $this->_('If disabled, related videos will come from the same channel as the video that was just played.'),
				'options' => [
					'' => '',
					'0' => $this->_('Show from the same channel'),
					'1' => $this->_('Show from any channel'),
				],
			],
		] as $name => $data) {
			$yt->add($this->inputfieldData('yt', $name, $data));
		}

		$inputfields->add($yt);


		// Vimeo Options
		$info = 'https://developer.vimeo.com/api/oembed/videos#table-2';
		$vm = $modules->get('InputfieldFieldset');
		$vm->label = $this->_('Vimeo Options');
		$vm->icon = 'vimeo';
		$vm->notes = sprintf($this->_('More information: %s'), "[$info]($info)");
		$vm->collapsed = 1;

		foreach([
			// id or url
			'autopause' => [
				'label' => $this->_('Autopause'),
				'description' => $this->_('Whether to pause the current video when another Vimeo video on the same page starts to play. Disable to permit simultaneous playback of all the videos on the page.'),
			],
			'autoplay' => [
				'label' => $this->_('Autoplay'),
				'description' => $this->_('Whether to start playback of the video automatically. This feature might not work on all devices.'),
			],
			'byline' => [
				'label' => $this->_('Byline'),
				'description' => $this->_("Whether to display the video owner's name."),
			],
			'color' => [
				'type' => 'text',
				'label' => $this->_('Color'),
				'description' => sprintf($this->_("The hexadecimal color value of the playback controls, which is normally %s. The embed settings of the video might override this value."), '00ADEF'),
				'placeholder' => '00ADEF',
			],
			'dnt' => [
				'label' => $this->_('Do Not Track'),
				'description' => $this->_('Whether to prevent the player from tracking session data, including cookies.'),
				'notes' => $this->_('Keep in mind that enabling this also blocks video stats.'),
			],
			'fun' => [
				'label' => $this->_('Informal error messages'),
				'description' => $this->_('Whether to disable informal error messages in the player, such as *Oops*.'),
			],
			'loop' => [
				'label' => $this->_('Loop'),
				'description' => $this->_('Whether to restart the video automatically after reaching the end.'),
			],
			'muted' => [
				'label' => $this->_('Muted'),
				'description' => $this->_('Whether the video is muted upon loading. Enabling this is required for the autoplay behavior in some browsers.'),
			],
			'playsinline' => [
				'label' => $this->_('Play inline'),
				'description' => $this->_('Whether the video plays inline on supported mobile devices. Disable to force the device to play the video in fullscreen mode instead.'),
			],
			'portrait' => [
				'label' => $this->_('Portrait'),
				'description' => $this->_("Whether to display the video owner's portrait."),
			],
			'responsive' => [
				'label' => $this->_('Responsive'),
				'description' => $this->_('Whether to return a *responsive embed code*, or one that provides intelligent adjustments based on viewing conditions.'),
			],
			'texttrack' => [
				'type' => 'text',
				'label' => $this->_('Text track'),
				'description' => $this->_('The text track to display with the video. Specify the text track by its language code (en), the language code and locale (en-US), or the language code and kind (en.captions).'),
				'notes' => implode("\n", [
					$this->_('For this argument to work, the video must already have a text track of the given type.'),
					$lang,
				]),
			],
			'title' => [
				'label' => $this->_('Title'),
				'description' => $this->_('Whether the player displays the title overlay.'),
			],
			'transparent' => [
				'label' => $this->_('Transparent'),
				'description' => $this->_('Whether the responsive player and transparent background are enabled.'),
			],
		] as $name => $data) {
			$vm->add($this->inputfieldData('vm', $name, $data));
		}

		$inputfields->add($vm);

		// Currently cached queries list
		$query = $this->wire('database')->prepare('SELECT name FROM caches WHERE name LIKE :name');
		$query->bindValue(':name', wireClassName($tf, false) . '__%');
		$query->execute();
		$c = $query->rowCount();
		if($c) {

			$clear = $modules->get('InputfieldSubmit');
			$clear->attr('name+id', 'clearCache');
			$clear->value = $this->_('Clear Cache');

			$inputfields->add([
				'type' => 'markup',
				'label' => $this->_('Cache'),
				'value' => $clear->render(),
				'description' => sprintf(
					$this->_n('There is %d cached video.', 'There are %d cached videos.', $c),
					$c
				),
				'icon' => 'files-o',
				'collapsed' => 1,
			]);
		}

		return $inputfields;
	}

	/**
	 * Get inputfield data
	 *
	 * @param string $key
	 * @param string $name
	 * @param array $data
	 * @return array
	 *
	 */
	protected function inputfieldData($key, $name, array $data) {
		$data['type'] = $data['type'] ?? 'select';
		$data['name'] = "{$key}_{$name}";
		$data['collapsed'] = $data['collapsed'] ?? 2;
		if($data['type'] == 'select') {
			$data['options'] = $data['options'] ?? [
				'' => '',
				'0' => $this->_('Disable'),
				'1' => $this->_('Enable'),
			];
		}
		return $data;
	}
}
