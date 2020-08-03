# Textformatter Video Markup
Render oEmbed data from YouTube/Vimeo URLs. Based on [TextformatterVideoEmbed](https://modules.processwire.com/modules/textformatter-video-embed/) by Ryan Cramer and [TextformatterVideoEmbedOptions](https://modules.processwire.com/modules/textformatter-video-embed-options/) by Steffen Henschel.

## Requirements
* ProcessWire >= 3.0.148
* PHP >= 7

## Installation
1. Download the [zip file](https://github.com/nbcommunication/TextformatterVideoMarkup/archive/master.zip) at Github or clone the repo into your `site/modules` directory.
2. If you downloaded the zip file, extract it in your `sites/modules` directory.
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
- `url`: The requested URL.
- `embedUrl`: The embed URL.
- `class`: **yt** or **vm**. Useful for styling YouTube/Vimeo markup differently.

#### Debugging
If `$config->debug` is set to `true`, you can output a table of the data returned by the oEmbed endpoint using the `{debug}` placeholder.

#### Example - Thumbnail image with UIKit Lightbox
```html
<figure data-uk-lightbox>
    <a href="{url}" data-poster="{thumbnail_url}" data-attrs="width: {width}; height: {height}">
        <img src="{thumbnail_url}" alt="{title}">
    </a>
</figure>
{debug}
```
*In debug mode, a table of the oEmbed data is appended.*

### Video Options
- Max Width: The video width.
- Max Height: The video height.
- Empty Value: The value that will be rendered if no response is received from the oEmbed endpoint.

For the empty value, the following placeholders can be used:
- `{url}`: Outputs the requested URL.
- `{link}`: Outputs the requested URL as a link.

### YouTube/Vimeo Options
These allow global configuration of videos. Not all options are available, just those that could be useful to set globally. For example, YouTube allows `start` and `end` parameter options - it wouldn't make sense to set these for every video on your site!

You can override the global defaults in the URL request e.g. https://www.youtube.com/watch?v=ScMzIvxBSi4&controls=0&color=white. The exception to this is YouTube's privacy-enhanced mode, which is not a paramater option, but a different URL.

If multi-language support is enabled, language paramaters will default to the name of the user's language e.g. https://www.youtube.com/watch?v=ScMzIvxBSi4&cc_lang_pref=fr&hl=fr.

More information on these options can be found here: [YouTube](https://developers.google.com/youtube/player_parameters#Parameters) / [Vimeo](https://developer.vimeo.com/api/oembed/videos#table-2)

Note: YouTube's `rel=0` doesn't behave the way it used to - see the link above for more information.

### Cache
Any data returned by the oEmbed endpoints is cached permanently unless cleared manually, which you can do so here.

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
