# Textformatter Video Markup
Render oEmbed data from YouTube/Vimeo URLs. Based on [TextformatterVideoEmbed](https://modules.processwire.com/modules/textformatter-video-embed/) by Ryan Cramer and [TextformatterVideoEmbedOptions](https://modules.processwire.com/modules/textformatter-video-embed-options/) by Steffen Henschel.

## Requirements
* ProcessWire >= 3.0.148
* PHP >= 7

## Installation
1. Download the [zip file](https://github.com/nbcommunication/TextformatterVideoMarkup/archive/master.zip) at Github or clone the repo into your `site/modules` directory.
2. If you downloaded the zip file, extract it in your `site/modules` directory.
3. In your admin, go to Modules > Refresh, then Modules > New, then click on the Install button for this module.

## How to use
- Edit the field you will be placing videos in. This can be any Text field e.g. Text, Textarea (CKEditor or not), URL etc.
- On the *Details* tab, select "Video markup for YouTube/Vimeo" in *Text Formatters* and **Save**.
- Edit a page using the field you've edited and paste in YouTube and/or Vimeo video URLs. If the field is using CKEditor, make sure each URL is on its own paragraph.

## Configuration

### Markup
This is the template that will be used to render the [oEmbed](https://oembed.com) data. It does this through the use of placeholders wrapped in curly brackets. Each endpoint should provide:

- `type`: **video**.
- `version`: **1.0**.
- `title`: A text title, describing the video.
- `author_name`: The name of the author/owner of the video.
- `author_url`: A URL for the author/owner of the video.
- `provider_name`: **YouTube** or **Vimeo**.
- `provider_url`: **https://www.youtube.com/** or **https://vimeo.com/**.
- `thumbnail_url`: A URL to a thumbnail image for the video.
- `thumbnail_width`: The width of the thumbnail.
- `thumbnail_height`: The height of the thumbnail.
- `html`: The HTML embed code.
- `width`: The width in pixels required to display the video.
- `height`: The height in pixels required to display the video.

Vimeo also returns a number of custom properties. For more information on these please see [https://developer.vimeo.com/api/oembed/videos#table-3](https://developer.vimeo.com/api/oembed/videos#table-3).

These additional placeholders are also available:
- `video_id`: The ID of the video
- `url`: The requested URL.
- `embedUrl`: The embed URL.
- `class`: **yt** or **vm**. Useful for styling YouTube/Vimeo markup differently.
- `params`: The query string extracted from the requested URL.
- `lite`: A pre-rendered facade. See *Facades* below.

#### YouTube Data API
Adding a [YouTube Data API (v3)](https://developers.google.com/youtube/v3/getting-started) key to the *YouTube Options* extends the oEmbed data for YouTube videos with the placeholders listed above. Restrict the key to the YouTube Data API v3 in the Google Cloud Console. Requests are made server-side, so restrict it by IP address rather than HTTP referrer. The key is sent in a request header, so it never appears in a URL.

API responses are cached separately from the oEmbed data, for the period set in *YouTube Data API Cache*. The default is 30 days, the longest the [YouTube API Services terms](https://developers.google.com/youtube/terms/developer-policies) allow API data to be stored without being refreshed. Set a shorter period if you want statistics like the view count to stay current. Each video costs 1 unit of the daily API quota per cache refresh.

Failed requests (e.g. an invalid key or exceeded quota) are logged to the `textformatter-video-markup` log and cached for an hour. During that time the oEmbed data is rendered without the extra placeholders.

### Facades
The preferred way of loading 3rd-party resources is via a facade: [https://developer.chrome.com/docs/lighthouse/performance/third-party-facades](https://developer.chrome.com/docs/lighthouse/performance/third-party-facades).

This module creates the markup appropriate for [https://github.com/paulirish/lite-youtube-embed](lite-youtube-embed) and [https://github.com/luwes/lite-vimeo-embed](lite-vimeo-embed) and it can be accessed via the `lite` property. You will need to implement the required assets yourself for this to work.

### Cache
Any data returned by the oEmbed endpoints is cached for the period set in *oEmbed Cache* (by default, permanently) unless cleared manually, which you can do so here. Failed requests (e.g. a deleted or private video) are logged to the `textformatter-video-markup` log and cached for an hour.

## Hooking
The `render()` method is hookable, allowing you to customise rendering on a per page, per field basis.

```php
// in site/ready.php
$wire->addHookBefore('TextformatterVideoMarkup::render', function(HookEvent $event) {

	// Arguments
	$tpl = $event->arguments(0); // string: The markup template
	$data = $event->arguments(1); // array: The oEmbed data
	$url = $event->arguments(2); // string: The requested URL
	$emptyValue = $event->arguments(3); // string: The empty value used if no data is returned

	// Object properties
	$page = $event->object->page; // Page: The page
	$field = $event->object->field; // Field: The field
	$html = $event->object->html; // bool: Is it HTML being parsed, or plain text?

	// Example 1 - Replace the thumbnail image
	if($field->name == 'video' && $page->hasField('images') && $page->images->count) {
		$data['thumbnail_url'] =  $page->images->first->url;
		$event->arguments(1, $data);
	}

	// Example 2 - Set empty values by fieldtype
	switch((string) $field->type) {
		case 'FieldtypeTextarea':
			$msg = sprintf(__('Sorry, the video (%s) could not be rendered.'), $url);
			$emptyValue = $html ? '<p>' . strip_tags($msg) . '</p>' : $msg;
			break;
		case 'FieldtypeURL':
			$event->arguments(0, '{title}'); // Also set the tpl
			$emptyValue = '{link}';
			break;
	}
	$event->arguments(3, $emptyValue);
});
```

## Notes
If the data cannot be retrieved, e.g. the video is private, a blank string is returned.
