<?php require_once('couch/cms.php');?>

<cms:content_type 'application/json' />


<cms:capture into='response' is_json='1'>{
	"success" : true,
	"message" : "Reached API"
}</cms:capture>


<!--
	:: FUNCTIONS
	| 'dd' -- die and dump, for debugging
	| 'set-this' -- for use within Couch arrays for converting given variables into key / value pairs
	| 'curl' -- fetch content with http requests
	| 'print-r-to-array' -- converts a PHP print_r dump back into an array
	| 'dump-to-array' -- converts a CouchCMS HTML dump into an array
	| 'post-or-get' -- returns POST or GET data
	| 'resource-by-id' -- fetch info about a given Couch page ID (including its parent template)
	| 'api-response' -- aborts the script and returns a JSON response for the API
	| 'api-abort' -- abrots the script and returns success: false by default, usually used for debugging
-->


<cms:func 'dd' message='' all='0'>
	<cms:abort>
		<cms:if message>
			<cms:show message />
		<cms:else_if all='1' />
			<cms:dump_all />
		<cms:else />
			<cms:dump />
		</cms:if>
	</cms:abort>
</cms:func>




<cms:func 'api-respond'>
	<cms:abort>
		<cms:show response as_json='1' />
	</cms:abort>
</cms:func>




<cms:func 'api-abort' message='Request failed'>
	<cms:set response.message=message scope='global' />
	<cms:php>
		global $CTX;
		$arr = $CTX->get('response');
		$arr['success'] = false;
		$CTX->set('response', $arr, 'global');
	</cms:php>
	<cms:call 'api-respond' />
</cms:func>




<cms:func 'curl'
	url="<cms:add_querystring k_site_link querystring='view=json' />"
	method='get'
	headers="Content-type: application/x-www-form-urlencoded"
	is_json=''
	data=''
	auth="username:password"
	into="curl-response"
>

	<cms:capture into=into is_json='1'>[]</cms:capture>
	<cms:php>
		
		global $CTX;

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, "<cms:show url />");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper("<cms:show method />"));
		curl_setopt($ch, CURLOPT_HTTPHEADER, explode(" | ", "<cms:show headers />"));
		$data = json_decode($CTX->get('data'));
		if(isset($data)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $CTX->get('is_json') == '1' ? json_encode($data) : http_build_query($data));
		}
		curl_setopt($ch, CURLOPT_USERPWD, "<cms:show auth />");
		curl_setopt($ch, CURLOPT_USERAGENT, "CouchCMS " . $CTX->get('k_cms_version') );
		curl_setopt($ch, CURLOPT_REFERER, $CTX->get('k_page_link') );

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		$response = curl_exec($ch);
		$CTX->set("<cms:show into />", $FUNCS->json_decode($response), 'global');
		curl_close($ch);

	</cms:php>

</cms:func>




<cms:func 'print-r-to-array' string='' into='array-response'>
	<cms:capture into=array-response is_json='1'>{}</cms:capture>
	<cms:php>
		global $CTX;
		if (!function_exists('reverse_reverse')) {
			function reverse_reverse($in) {
			    $lines = explode("\n", trim($in));
			    if (trim($lines[0]) != 'Array') {
			    	return $in;
			    } else {
			        if (preg_match("/(\s{5,})\(/", $lines[1], $match)) {
			            $spaces = $match[1];
			            $spaces_length = strlen($spaces);
			            $lines_total = count($lines);
			            for ($i = 0; $i < $lines_total; $i++) {
			                if (substr($lines[$i], 0, $spaces_length) == $spaces) {
			                    $lines[$i] = substr($lines[$i], $spaces_length);
			                }
			            }
			        }
			        array_shift($lines);
			        array_shift($lines);
			        array_pop($lines);
			        $in = implode("\n", $lines);
			        preg_match_all("/^\s{4}\[(.+?)\] \=\> /m", $in, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
			        $pos = array();
			        $previous_key = '';
			        $in_length = strlen($in);
			        foreach ($matches as $match) {
			            $key = $match[1][0];
			            $start = $match[0][1] + strlen($match[0][0]);
			            $pos[$key] = array($start, $in_length);
			            if ($previous_key != '') $pos[$previous_key][1] = $match[0][1] - 1;
			            $previous_key = $key;
			        }
			        $ret = array();
			        foreach ($pos as $key => $where) {
			            $ret[$key] = reverse_reverse(substr($in, $where[0], $where[1] - $where[0]));
			        }
			        return $ret;
			    }
			}
		}

		if (!function_exists('htmlPrintRToArray')) {
			function htmlPrintRToArray($input) {
			    $input = html_entity_decode($input);
			    $input = strip_tags($input);
			    return reverse_reverse($input);
			}
		}

		$html = $CTX->get('string');
		$array = htmlPrintRToArray($html);
		$CTX->set($CTX->get('into'), $array, 'global');
	</cms:php>
</cms:func>




<cms:func 'dump-to-array' dump='' into='dump-response'>
	<cms:php>
		global $CTX;

		$html = $CTX->get('dump');
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);
		libxml_clear_errors();
		$root = $dom->getElementsByTagName('ul')->item(0);

		if(!function_exists('parseUL')) {
			function parseUL($node) {
			    $result = array();
			    foreach ($node->childNodes as $child) {
			        if ($child->nodeName == 'li') {
			            $key = trim($child->getElementsByTagName('b')->item(0)->nodeValue, ': ');
			            $value = $child->nodeValue;
			            if (strpos($value, $key.': ') === 0) {
			                $value = substr($value, strlen($key)+2);
			            }
			            if ($child->getElementsByTagName('ul')->length > 0) {
			                $value = parseUL($child->getElementsByTagName('ul')->item(0));
			            }
			            $result[$key] = $value;
			        }
			    }
			    return $result;
			}
		}
	    $CTX->set('dump-response', parseUL($root), 'global');
	</cms:php>
</cms:func>




<cms:func 'post-or-get' var=''><cms:php>
	global $CTX;
	<cms:set tmp_var="<cms:gpc var method='get' />" />
	<cms:if tmp_var=''><cms:set tmp_var="<cms:gpc var method='post' />" /></cms:if>
	echo $CTX->get('tmp_var');
</cms:php></cms:func>




<cms:func 'resource-by-id' id='' into='resource-response'>
	<cms:set id_is_valid="<cms:validate value=id validator='non_zero_integer' />" />
	<cms:if id_is_valid>
		<cms:query sql="SELECT * FROM <cms:php>echo K_TBL_PAGES;</cms:php> WHERE id='<cms:show id />'">
			<cms:capture into=into is_json='1'>{
				"id" : "<cms:show id />",
				"template" : "<cms:templates><cms:if k_template_id=template_id><cms:show k_template_name /></cms:if></cms:templates>"
			}</cms:capture>
		</cms:query>
	<cms:else />
		<cms:call 'api-abort' message='Invalid ID' />
	</cms:if>
</cms:func>






<!-- - - - - - - - - - - - - - - - -->





<!--
	:: VARIABLES
	| 'config' -- brings in couch/config.php constancts (K_API_KEY, etc)
	| 'request' -- the array that gets built based on internal Couch data and is used to inform the subsequent response
	| 'response' -- the array that gets built and ultimately returned to the client
-->


<cms:capture into='config' is_json='1'>{
	"api_key" : "<cms:php>if(defined('K_API_KEY')){echo(K_API_KEY);}</cms:php>"
}</cms:capture>




<cms:capture into='request' is_json='1'>{
	"uri" : "<cms:php>echo($_SERVER['REQUEST_URI']);</cms:php>",
	"endpoint" : "<cms:gpc 'q' method='get' />",
	"get" : {},
	"post" : {},
	"api" : {
		"id" : "<cms:call 'post-or-get' var='id' />",
		"template" : {},
		"routes" : {}
	},
	"api_key" : "<cms:call 'post-or-get' var='api_key' />"
}</cms:capture>
<!-- Make sure request.api.id is valid -->
<cms:set id_is_valid="<cms:validate request.api.id validator='non_zero_integer' />" />
<cms:if request.api.id && id_is_valid='0'>
	<cms:call 'api-abort' message='Invalid ID' />
</cms:if>




<cms:capture into='system' is_json='1'>{
	"version" : {
		"latest" : "0"
	},
	"timing" : {
		"timestamp" : "<cms:date format='Y-m-d H:i:s' />",
		"datetime" : "<cms:date format='c' />",
		"date" : "<cms:date format='Y-m-d' />",
		"time" : "<cms:date format='H:i:s' />",
		"day" : "<cms:date format='l' />",
		"day_short" : "<cms:date format='D' />",
		"month" : "<cms:date format='F' />",
		"month_short" : "<cms:date format='M' />",
		"year" : "<cms:date format='Y' />",
		"year_short" : "<cms:date format='y' />",
		"day_of_week" : "<cms:date format='N' />",
		"day_of_year" : "<cms:date format='z' />",
		"week_of_year" : "<cms:date format='W' />",
		"week_of_month" : "<cms:date format='W' />",
		"leap_year" : "<cms:date format='L' />",
		"dst" : "<cms:date format='I' />",
		"unix_timestamp" : "<cms:date format='U' />"
	},
	"endpoints" : {}
}</cms:capture>




<cms:call 'print-r-to-array' string="<cms:dump_routes />" into='_tmpRoutes' />
<cms:php>
	global $CTX;
	$arr = $CTX->get('_tmpRoutes')['<cms:show k_template_name />'];
	$CTX->set('_tmpRoutes', $arr, 'global');
</cms:php>
<cms:each _tmpRoutes as='_tmpRoute'>
	<cms:capture into='_tmpRouteObject' is_json='1'>
		{
			"name" : "<cms:show _tmpRoute.name />",
			"path" : "<cms:show _tmpRoute.path />"
		}
	</cms:capture>
	<cms:put var="_tmpRoutes.<cms:show k_count />" value=_tmpRouteObject scope='global' />
</cms:each>
<cms:set system.endpoints=_tmpRoutes scope='global' />





<!-- - - - - - - - - - - - - - - - - -->





<!--
	:: AUTHORIZATION FILTERS
	| 1. check if user is Couch admin
	| 2. check if user has provided valid API key
	| 3. if not cleared, abort the request
-->


<cms:if k_user_access_level lt '7'>
	<cms:if config.api_key=''>
		<cms:call 'api-abort' message='API unavailable (API key not set).' />
	</cms:if>
	<cms:if request.api_key ne config.api_key>
		<cms:call 'api-abort' message='Unauthorized' />
	</cms:if>
</cms:if>





<!-- - - - - - - - - - - - - - - - -->





<!--
	:: REQUEST.API.TEMPLATES
	| This puts all the basic template info into context by looping through the cms:templates array.
	| For each template, we also capture its respective fields with cms:db_fields
-->


<cms:capture into='request.api.templates' is_json='1'>[]</cms:capture>
<cms:set templates_count='0' />

<cms:templates skip_system='0'>

	<cms:capture into='request.api.templates.' is_json='1'>{
		"id" : "<cms:show k_template_id />",
		"name" : "<cms:show k_template_name />",
		"title" : "<cms:show k_template_title />",
		"basename" : "<cms:php>echo basename("<cms:show k_template_name />", '.php');</cms:php>",
		"fields" : {}
	}</cms:capture>

	<cms:set fields_count='0' />
	<cms:db_fields masterpage=k_template_name skip_system='1' skip_deleted='1' names="NOT k_comments_open, k_access_level, k_page_folder_id" types="NOT group">
		<cms:capture into='fields_object' is_json='1'>{}</cms:capture>
		<cms:set field_keys="id | template_id | name | label | desc | data | required | type | max_length | masterpage | field | validator | validator_msg | opt_values | opt_selected | min_length | order | separator | val_separator | group" />
		<cms:each field_keys as='key'>
			<cms:capture into='key_value'><cms:get var=key /></cms:capture>
			<cms:if key_value>
				<cms:capture into="fields_object.<cms:show key />"><cms:get var=key /></cms:capture>
			</cms:if>
			<cms:if key='type' && key_value='__mosaic'>
				<cms:php>
					global $CTX;
					$data = $CTX->get('schema');
					$decoded = unserialize($data);
					foreach ($decoded as $key => $value) {
						foreach ($value as $k => $v) {
							$decoded[$key][$k] = base64_decode($v);
						}
					}
					$CTX->set('decoded_schema', $decoded, 'global');
				</cms:php>
				<cms:each decoded_schema as='decoded_field'>
					<cms:put var="decoded_schema.<cms:show key />.fields" value='[]' is_json='1' scope='global' />
					<cms:db_fields masterpage=decoded_field.tpl_name skip_system='1'>
						<cms:capture into='tile_fields_object' is_json='1'>{}</cms:capture>
						<cms:each field_keys as='tile_key'>
							<cms:capture into='tile_key_value'><cms:get var=tile_key /></cms:capture>
							<cms:if tile_key_value>
								<cms:capture into="tile_fields_object.<cms:show tile_key />"><cms:get var=tile_key /></cms:capture>
							</cms:if>
						</cms:each>
						<cms:put var="decoded_schema.<cms:show key />.fields." value=tile_fields_object scope='global' />
					</cms:db_fields>
				</cms:each>
				<cms:capture into="fields_object.tiles" is_json='1'><cms:show decoded_schema as_json='1' /></cms:capture>
			</cms:if>
		</cms:each>
		<cms:put var="request.api.templates.<cms:show templates_count />.fields." value=fields_object scope='global' />
		<cms:incr fields_count />
	</cms:db_fields>

	<cms:incr templates_count />

</cms:templates>





<!-- - - - - - - - - - - - - - - - -->





<!--
	:: DEFINE THE API TEMPLATE
	| This is the template that will be used to render the API response.
	| No editables here as of now, just route definitions.
-->


<cms:template title='API' hidden='1' routable='1'>

	<cms:route
		name='v0.index'
		path='v0/index/'
	/>

	<cms:route
		name='v0.templates'
		path='v0/templates/'
	/>

	<cms:route
		name='v0.list'
		path='v0/{:template}/'
	/>

	<cms:route
		name='v0.doc'
		path='v0/{:template}/doc/'
	/>

	<cms:route
		name='v0.create'
		path='v0/{:template}/create/'
	/>

	<cms:route
		name='v0.edit'
		path='v0/{:template}/edit/'
	/>

	<cms:route
		name='v0.delete'
		path='v0/{:template}/delete/'
	/>

</cms:template>
<cms:match_route debug='0' />





<!-- - - - - - - - - - - - - - - - -->





<!--
	:: LOGICAL TEMPLATE FILTERS
	| ## Check rt_template
	| Check if rt_template was passed. If so, we check the request.api.template array
	| for the matching template. If it doesn't exist, we abort and let the client know that
	| template does not presently exist. While we're there, we put the template object into
	| the request.api.template object.
	| 
	| ## Check 'id'
	| Earlie, we set request.api.id (passed in via POST or GET). If given, we check that the
	| 'id' belongs to that template. If not, we abort and let the client know that the id
	| does not belong to the template.
-->


<cms:if rt_template>
	<cms:set template_found='0' />
	<cms:each request.api.templates as='template'>
		<cms:if rt_template=template.basename>
			<cms:put var="request.api.template" value=template scope='global' />
		</cms:if>
	</cms:each>
	<cms:if request.api.template.id=''>
		<cms:call 'api-abort' message="Given template, '<cms:show rt_template />', does not exist." />
	</cms:if>
</cms:if>




<cms:if request.api.id>
	<cms:set resource_exists="<cms:pages masterpage=request.api.template.name id=request.api.id count_only='1' />" />
	<cms:if resource_exists='0'>
		<cms:call 'api-abort' message="Given ID, '<cms:show request.api.id />', does not belong to given template, '<cms:show request.api.template.basename />'." />
	</cms:if>
</cms:if>





<!-- - - - - - - - - - - - - - - - -->




<!--
	:: REQUEST TEMPLATE VARIABLES
	| Here, we capture the variables specifically related to the request.api.template object
-->


<cms:call 'print-r-to-array' string="<cms:dump_routes />" into='routes' />
<cms:php>
	global $CTX;
	$arr = $CTX->get('routes')['<cms:show request.api.template.name />'];
	$CTX->set('routes', $arr, 'global');
</cms:php>





<!-- - - - - - - - - - - - - - - - -->





<!--
	:: RESPONSE BASICS
	| Here, we fill the response object with basic information that will be used
	| in the API response, including the 'route' name and the 'template' object.
-->


<cms:set response.apiEndpoint=request.endpoint />
<cms:set repsonse.template=request.api.template scope='global' />





<!-- - - - - - - - - - - - - - - - -->


<!--
	:: RESPONSE FIELDS
	| Here, we capture the fields from the template and put them into the response object.
	| We also capture the field keys and put them into the response object.
	| A special variable request.postables is created to hold the fields that are permitted
	| to be posted to the API (particularly useful in the API 'create' and 'edit' routes).
-->


<cms:capture into='request.resource' is_json='1'>{
	"postables" : [],
	"postings" : {}
}</cms:capture>

<cms:each var="k_page_title | k_page_name | k_page_date">
	<cms:capture into='request.resource.postables.' is_json='1'>{
		"name" : "<cms:show item />",
		"type" : "text"
	}</cms:capture>
</cms:each>

<cms:each request.api.template.fields as='field'>
	<cms:put var="request.resource.postables." value=field scope='global' />
</cms:each>

<cms:each request.resource.postables as='postable'>
	<cms:set matched_posting="<cms:gpc postable.name method='post' />" />
	<cms:if matched_posting>
		<cms:put var="request.resource.postings.<cms:show postable.name />" value=matched_posting scope='global' />
	</cms:if>
</cms:each>





<!-- - - - - - - - - - - - - - - - -->





<!--
	:: v0.INDEX
	| For the index route, we simply list all of the available routes.
-->

<cms:if k_matched_route='v0.index'>

	<cms:capture into='response.data' is_json='1'>{}</cms:capture>
	<cms:set response.message='Listing API routes.' scope='global' />

	<cms:capture into='response.data.usage' is_json='1'>
		{
			"synopsis" : "The GroveOS API is a REST inspired API that uses GET and POST HTTP verbs to perform CRUD operations on records within the system. All requests must be made via HTTPS. All responses are in JSON format.",
			"keyConcepts" : {
				"templates" : {
					"synopsis" : "Templates are representations of record types. Each template has its own set of system fields and custom fields. For instance, the People template has (1) system fields of k_page_title, k_page_date, and k_page_name and (2) custom fields of first_name and last_name whereas the Blog Posts template has (1) system fields of k_page_title, k_page_date, and k_page_name and (2) custom fields of content, tags, and author.",
					"examples" : [
						{
							"name" : "blog-posts.php",
							"title" : "Blog Posts",
							"exampleEnpoint" : "https://your-site.com/api.php?q=v0/blog-posts/edit/&id=242"
						},
						{
							"name" : "products.php",
							"title" : "Products",
							"exampleEnpoint" : "https://your-site.com/api.php?q=v0/products/&limit=40"
						},
						{
							"name" : "people.php",
							"title" : "People",
							"exampleEnpoint" : "https://your-site.com/api.php?q=v0/people/&where=first_name.eq.John|age.gt.18"
						},
						{
							"name" : "comments",
							"title" : "Comments",
							"exampleEnpoint" : "https://your-site.com/api.php?q=v0/comments/delete/&id=212833"
						}
					]
				},
				"systemFields" : {
					"synopsis" : "System fields are fields that are required and optionally automatically created for each record. Each record has a 'k_page_title' (the title), 'k_page_name' (can be a random string or lowercase hyphenated version of the title), and 'k_page_date' (the publish date in Y-m-d H:i:s format). We can declare our own values here or use the default values.",
					"examples" : [
						{
							"name" : "k_page_title",
							"type" : "text",
							"example" : "John Doe",
							"default" : "random_string"
						},
						{
							"name" : "k_page_name",
							"type" : "text",
							"example" : "john-doe",
							"default" : "random_string"
						},
						{
							"name" : "k_page_date",
							"type" : "datetime",
							"example" : "2017-01-01 00:00:00",
							"default" : "current_datetime"
						}
					]
				},
				"customFields" : {
					"synopsis" : "Each record has custom fields defined by the template. These fields are documented in the 'fields' array in the template's v0.doc endpoint. Custom fields have no default value.",
					"examples" : [
						{
							"name" : "content",
							"type" : "richtext",
							"example" : "<h2>About our company</h2><p>Our company delivers innovative solutions and exceptional products/services to meet client needs. With a dedicated team, we prioritize customer satisfaction, building strong relationships based on trust. Our passion for innovation drives positive market impact.</p>"
						},
						{
							"name" : "tags",
							"type" : "relation",
							"template" : "tags",
							"example" : "8322,3882,1434"
						},
						{
							"name" : "author",
							"type" : "relation",
							"template" : "people",
							"example" : "7234"
						}
					]
				},
				"records" : {
					"synopsis" : "Records are instances of templates. For instance, a record of the People template might be John Doe with k_page_title of 'John Doe', a first_name of 'John', and a last_name of 'Doe'. A record of the Blog Posts template might be a blog post with k_page_title of 'About our company', content of '<p>This is a post about our company.</p>', and tags of '242,288'."
				},
				"endpoints" : {
					"synopsis" : "Endpoints are the URLs that are used to interact with the API. There are four endpoints: v0.index (this one), v0.doc, v0.list, v0.create, v0.edit, and v0.delete. Endoints are documented below.",
					"actions" : {
						"doc" : {
							"synopsis" : "Here, we document the fields of each template. For instance, if we wanted to get the documentation for the Blog Posts template, we would use the following endpoint: https://your-site.com/api.php?q=v0/blog-posts/doc/.",
							"examples" : [
								{
									"url" : "https://your-site.com/api.php?q=v0/blog-posts/doc/",
									"description" : "Document the blog-posts.php template."
								},
								{
									"url" : "https://your-site.com/api.php?q=v0/people/doc/",
									"description" : "Document the people.php template."
								}
							]
						},
						"list" : {
							"synopsis" : "Here, we can list records of a given template. For instance, if we wanted to get all blog posts, we would use the following endpoint: https://your-site.com/api.php?q=v0/blog-posts/list/. Multiple records can be listed by separating them with a comma (','). List actions can be combined with the 'limit' and 'offset' parameters to paginate results.",
							"examples" : [
								{
									"url" : "https://your-site.com/api.php?q=v0/blog-posts/doc/",
									"description" : "List the first 10 blog posts."
								},
								{
									"url" : "https://your-site.com/api.php?q=v0/blog-posts/doc/&limit=40",
									"description" : "List the first 40 blog posts."
								},
								{
									"url" : "https://your-site.com/api.php?q=v0/blog-posts/doc/&limit=40&offset=40",
									"description" : "List the next set of 40 blog posts."
								},
								{
									"url" : "https://your-site.com/api.php?q=v0/blog-posts/doc/&id=243",
									"description" : "List the blog post with ID 243."
								},
								{
									"url" : "https://your-site.com/api.php?q=v0/blog-posts/doc/&id=243,244,245",
									"description" : "List the blog posts with ID 243, 244, and 245."
								},
								{
									"url" : "https://your-site.com/api.php?q=v0/blog-posts/doc/&where=tags.eq.242",
									"description" : "List all blog posts with the tag '242'."
								},
								{
									"url" : "https://your-site.com/api.php?q=v0/blog-posts/doc/&where=tags.eq.242&limit=40",
									"description" : "List the blog posts with the tag '242', but limit to the first 40."
								}
							]
						},
						"create" : {
							"synopsis" : "Here, we can create a new record of a given template. For instance, if we wanted to create a new blog post, we would use the following endpoint: https://your-site.com/api.php?q=v0/blog-posts/create/. Note that create endoint requires POST values and won't accept GET parameters. For instance, if we wanted to create a new blog post with the title 'About our company', this following endpoint would not work: https://your-site.com/api.php?q=v0/blog-posts/create/&k_page_title=About%20our%20company. Instead, POST a form-urlencoded string either via JS fetch, curl, or similar HTTP tool.",
							"examples" : [
								{
									"url" : "https://your-site.com/api.php?q=v0/blog-posts/create/",
									"description" : "Create a new blog post with title 'About our Company' and related tags '242,288'.",
									"instructions" : {
										"viaCurl" : "curl -s -d 'k_page_title=About%20our%20company&tags=242,288' -d api_key=$my-api-key https://your-site.com/api.php?q=v0/blog-posts/create/",
										"viaForm" : "Set up an HTML form with fields named 'k_page_title and 'tags', and submit a POST request to https://your-site.com/api.php?q=v0/blog-posts/create/."
									}
								},
								{
									"description" : "Create a new person with the name 'Julie Stewart'.",
									"url" : "https://your-site.com/api.php?q=v0/blog-posts/create/&k_page_title=Julie%20Stewart&first_name=Julie&last_name=Stewart",
									"instructions" : {
										"viaCurl" : "curl -s -d 'k_page_title=Julie%20Stewart&first_name=Julie&last_name=Stewart' -d api_key=$my-api-key https://your-site.com/api.php?q=v0/people/create/",
										"viaForm" : "Set up an HTML form with fields named 'k_page_title', 'first_name', and 'last_name', and submit a POST request to https://your-site.com/api.php?q=v0/people/create/."
									}
								}
							]
						},
						"edit" : {
							"synopsis" : "Here, we can edit a given record of a given template. For instance, if we wanted to edit the blog post with ID 243, we would use the following endpoint: https://your-site.com/api.php?q=v0/blog-posts/edit/&id=243. Note that edit endoint requires POST values and won't accept GET parameters outside of the record's ID. For instance, if we wanted to edit the blog post with ID 243 with the title 'About our company', this following endpoint would not work: https://your-site.com/api.php?q=v0/blog-posts/edit/&id=243&k_page_title=About%20our%20company. Instead, POST a form-urlencoded string either via JS fetch, curl, or similar HTTP tool.",
							"examples" : [
								{
									"url" : "https://your-site.com/api.php?q=v0/blog-posts/edit/&id=243",
									"description" : "Edit the blog post with ID 243 with title 'About our Company'.",
									"instructions" : {
										"viaCurl" : "curl -s -d 'k_page_title=About%20our%20company' -d api_key=$my-api-key https://your-site.com/api.php?q=v0/blog-posts/edit/&id=243",
										"viaForm" : "Set up an HTML form with a field named 'k_page_title', and submit a POST request to https://your-site.com/api.php?q=v0/blog-posts/edit/&id=243."
									}
								},
								{
									"description" : "Edit the person with ID 243 with the name 'Julie Stewart'.",
									"url" : "https://your-site.com/api.php?q=v0/people/edit/&id=243&k_page_title=Julie%20Stewart&first_name=Julie&last_name=Stewart",
									"instructions" : {
										"viaCurl" : "curl -s -d 'k_page_title=Julie%20Stewart&first_name=Julie&last_name=Stewart' -d api_key=$my-api-key https://your-site.com/api.php?q=v0/people/edit/&id=243",
										"viaForm" : "Set up an HTML form with fields named 'k_page_title', 'first_name', and 'last_name', and submit a POST request to https://your-site.com/api.php?q=v0/people/edit/&id=243."
									}
								}
							]
						},
						"delete" : {
							"synopsis" : {}
						}
					},
					"filters" : {
						"synopsis" : "Filters are used to filter the results of a request. For instance, if we wanted to get all blog posts with the tag '242', we would use the following endpoint: https://your-site.com/api.php?q=v0/blog-posts/&where=tags.eq.242. Multiple filters can be used by separating them with a pipe character ('|'). For instance, if we wanted to get all blog posts with the tag '242' and the tag '288', we would use the following endpoint: https://your-site.com/api.php?q=v0/blog-posts/&where=tags.eq.242|tags.eq.288. Filters can be combined with the 'limit' and 'offset' parameters to paginate results. For instance, if we wanted to get the 10th through 20th blog posts with the tag '242' and the tag '288', we would use the following endpoint: https://your-site.com/api.php?q=v0/blog-posts/&where=tags.eq.242|tags.eq.288&limit=10&offset=10.",
						"examples" : [
							{
								"name" : "where",
								"type" : "string",
								"example" : "tags.eq.242"
							},
							{
								"name" : "limit",
								"type" : "integer",
								"example" : "10"
							},
							{
								"name" : "offset",
								"type" : "integer",
								"example" : "10"
							}
						]
					}
				}
			}
		}
	</cms:capture>

	<cms:set response.data.endpoints=system.endpoints scope='global' />



<!--
	:: v0.TEMPLATES
	| For the templates route, we simply list all of the available templates.
	| Note: we strip down the template objects to just the basic info (no field data).
-->

<cms:else_if k_matched_route='v0.templates' />

	<cms:set response.message='Listing available templates.' scope='global' />
	<cms:each request.api.templates as='template'>
		<cms:capture into="request.api.templates.<cms:show k_count />.fields" is_json='1'>[
			<cms:each template.fields as='field'>
				{
					"name" : "<cms:show field.name />",
					"type" : "<cms:show field.type />"
				}<cms:if k_last_item='0'>,</cms:if>
			</cms:each>
		]</cms:capture>
	</cms:each>
	<cms:set response.data=request.api.templates scope='global' />



<!--
	:: v0.DOC
	| For the doc route, we show the request.api.template object as documentation.
	| Particularly helpful for use alongside AI LLMs and auto-generated documentation UIs.
-->

<cms:else_if k_matched_route='v0.doc' />

	<cms:set response.message="Viewing '<cms:show request.api.template.title />' documentation." scope='global' />
	<cms:set response.data=request.api.template scope='global' />



<!--
	:: v0.CREATE
	| For the create route, we create a new record, if the request is valid.
	| The request is valid if postables are set, and if the postables are valid.
-->
<cms:else_if k_matched_route='v0.create' />

	<cms:each request.resource.postings as='posting'>
		<cms:set postings_are_present='1' scope='global' />
	</cms:each>
	<cms:if postings_are_present>
		<cms:db_persist
			_masterpage=request.api.template.name
			_mode='create'
			_auto_title='0'
			k_page_name="<cms:if request.resource.postings.k_page_name><cms:show request.resource.postings.k_page_name /><cms:else /><cms:random_name /></cms:if>"
			k_page_title="<cms:if request.resource.postings.k_page_title><cms:show request.resource.postings.k_page_title /><cms:else /><cms:random_name /></cms:if>"
			k_page_date="<cms:if request.resource.postings.k_page_date><cms:show request.resource.postings.k_page_date /><cms:else /><cms:date format='Y-m-d H:i:s' /></cms:if>"
			_fields=request.resource.postings
		>
			<cms:if k_success>
				<cms:set response.message="Created '<cms:show request.api.template.title />' record (<cms:show k_last_insert_id />)." scope='global' />
				<cms:capture into='response.data' is_json='1'>{}</cms:capture>
				<cms:pages masterpage=request.api.template.name id=k_last_insert_id show_future_entries='1'>
					<cms:put var='response.data.k_page_id' value=k_last_insert_id scope='global' />
					<cms:put var='response.data.k_page_title' value=k_page_title scope='global' />
					<cms:put var='response.data.k_page_name' value=k_page_name scope='global' />
					<cms:put var='response.data.k_page_date' value=k_page_date scope='global' />
					<cms:each request.api.template.fields as='field'>
						<cms:put var="response.data.<cms:show field.name />" value="<cms:get field.name />" scope='global' />
					</cms:each>
				</cms:pages>
			<cms:else />
				<cms:call 'api-abort' message="<cms:each k_error><cms:show item /><cms:if k_last_item='0'> | </cms:if></cms:each>" />
			</cms:if>
		</cms:db_persist>
	<cms:else />
		<cms:call 'api-abort' message="Valid post values are required. Valid post values include: <cms:each request.resource.postables><cms:show item.name /><cms:if k_last_item='0'> | </cms:if></cms:each>" />
	</cms:if>



<!--
	:: v0.LIST
	| We start with an empty array, then fill it with the data from the template.
	| If we're in 'v0.list' route, we limit the results to 10. If request.api.id is given,
	| we limit the results to that resource, and if it isn't given, we limit the results
	| to the first 10 resources and set a 'has_more' variable to let the client know that
	| there are more resources available, if applicable.
-->

<cms:else_if k_matched_route='v0.list' />

	<cms:set response.message="Listing '<cms:show request.api.template.title />' records." scope='global' />

	<!-- Set possible query params -->
	<cms:put var='request.get.page_name' value="<cms:gpc 'page_name' method='get' />" scope='global' />
	<cms:put var='request.get.page_title' value="<cms:gpc 'page_title' method='get' />" scope='global' />
	<cms:put var='request.get.starts_on' value="<cms:gpc 'starts_on' method='get' />" scope='global' />
	<cms:put var='request.get.stops_before' value="<cms:gpc 'stops_before' method='get' />" scope='global' />
	<cms:put var='request.get.limit' value="<cms:gpc 'limit' method='get' />" scope='global' />
	<cms:if request.get.limit gt '100'><cms:call 'api-abort' message='Cannot request more than 100 records.' /></cms:if>
	<cms:put var='request.get.future_entries' value="<cms:gpc 'future_entries' method='get' />" scope='global' />
	<cms:put var='request.get.offset' value="<cms:gpc 'offset' method='get' />" scope='global' />
	<cms:put var='request.get.order' value="<cms:gpc 'order' method='get' />" scope='global' />
	<cms:put var='request.get.keywords' value="<cms:gpc 'keywords' method='get' />" scope='global' />
	<cms:put var='request.get.where' value="<cms:gpc 'where' method='get' />" scope='global' />
	<!-- Validate the where, else it will through an error -->
	<cms:capture into='request.get.formatted_where' is_json='1'>[]</cms:capture>
	<cms:each request.get.where as='statement' sep='|'>
		<cms:capture into='statement_object' is_json='1'>{}</cms:capture>
		<cms:each statement as='part' sep='.'>
			<cms:if k_count='0'><cms:put var="statement_object.key" value=part scope='global' /></cms:if>
			<cms:if k_count='1'><cms:put var="statement_object.operator" value="<cms:if part='eq'>=<cms:else_if part='gt' />><cms:else_if part='lt' /><<cms:else /><cms:call 'api-abort' message='Invalid where parameter operator. Operator must be eq, gt, or lt.' /></cms:if>" scope='global' /></cms:if>
			<cms:if k_count='2'><cms:put var="statement_object.value" value=part scope='global' /></cms:if>
		</cms:each>
		<!-- Validation -->
		<cms:set passed='0' />
		<cms:each request.api.template.fields as='field'>
			<cms:if field.name=statement_object.key>
				<cms:incr passed />
			</cms:if>
		</cms:each>
		<cms:if passed='0'>
			<cms:call 'api-abort' message="GET parameter '<cms:show statement_object.key />' does not exist as custom_field." />
		</cms:if>
		<cms:put var="request.get.formatted_where." value=statement_object is_json='1' scope='global' />
	</cms:each>

	<cms:capture into='response.paginateLimit'></cms:capture>
	<cms:capture into='response.totalRecords'></cms:capture>
	<cms:capture into='response.hasMore'></cms:capture>
	<cms:capture into='response.data' is_json='1'>[]</cms:capture>

	<cms:pages masterpage=request.api.template.name offset=request.get.offset order=request.get.order keywords=request.get.keywords custom_field="<cms:each request.get.formatted_where><cms:show item.key /><cms:show item.operator /><cms:show item.value /><cms:if k_last_item='0'>|</cms:if></cms:each>" limit="<cms:if request.get.limit><cms:show request.get.limit /><cms:else />10</cms:if>" id=request.api.id show_future_entries=request.get.future_entries>

		<cms:if k_paginated_top>
			<cms:php>
				global $CTX;
				$arr = $CTX->get('response');
				$arr['totalRecords'] = <cms:show k_total_records />;
				$arr['paginateLimit'] = <cms:show k_paginate_limit />;
				$CTX->set('response', $arr, 'global');
			</cms:php>
		</cms:if>

		<cms:if k_paginated_bottom>
			<cms:php>
				global $CTX;
				$arr = $CTX->get('response');
				$arr['hasMore'] = <cms:if k_total_records gt k_paginate_limit>true<cms:else />false</cms:if>;
				$CTX->set('response', $arr, 'global');
			</cms:php>
		</cms:if>
		
		<cms:no_results>
			<cms:php>
				global $CTX;
				$arr = $CTX->get('response');
				$arr['totalRecords'] = 0;
				$arr['hasMore'] = false;
				$CTX->set('response', $arr, 'global');
			</cms:php>
		</cms:no_results>
		
		<cms:set record_index="<cms:sub k_count '1' />" />
		<cms:capture into='response.data.' is_json='1'>{
			"id" : "<cms:show k_page_id />",
			"k_page_name" : "<cms:show k_page_name />",
			"k_page_title" : "<cms:show k_page_title />",
			"k_page_date" : "<cms:date format='Y-m-d H:i:s' />",
			"fields" : {}
		}</cms:capture>

		<cms:each request.api.template.fields as='field'>

			<cms:put var="response.data.<cms:show record_index />.fields.<cms:show field.name />" value="<cms:get field.name />" scope='global' />

			<cms:if field.type = '__mosaic'>
				
				<cms:capture into="response.data.<cms:show record_index />.fields.<cms:show field.name />" is_json='1'>{
					"rows" : []
				}</cms:capture>

				<cms:set row_index='0' />
				<cms:show_mosaic field.name>
					<cms:capture into="response.data.<cms:show record_index />.fields.<cms:show field.name />.rows." is_json='1'>{
						"type" : "<cms:show k_tile_name />",
						"label" : "<cms:show k_tile_label />",
						"fields" : []
					}</cms:capture>
					<cms:each field.tiles as='tile'>
						<cms:if tile.name=k_tile_name>
							<cms:each tile.fields as='tile_field'>
								<cms:put var="response.data.<cms:show record_index />.fields.<cms:show field.name />.rows.<cms:show row_index />.fields.<cms:show tile_field.name />" value="<cms:if tile_field.type='richtext'><cms:html_encode><cms:get tile_field.name /></cms:html_encode><cms:else /><cms:get tile_field.name /></cms:if>" scope='global' />
							</cms:each>
						</cms:if>

					</cms:each>
					<cms:incr row_index />
				</cms:show_mosaic>

			</cms:if>

		</cms:each>

	</cms:pages>



<!--
	:: v0.EDIT
	| For the edit route, we edit an existing record, if the request is valid (valid postables).
-->

<cms:else_if k_matched_route='v0.edit' />

	<cms:pages masterpage=request.api.template.name id=request.api.id>
		<cms:db_persist
			_masterpage=request.api.template.name
			_mode='edit'
			_page_id=request.api.id
			k_page_name="<cms:if request.resource.postings.k_page_name><cms:show request.resource.postings.k_page_name /><cms:else /><cms:show k_page_name /></cms:if>"
			k_page_title="<cms:if request.resource.postings.k_page_title><cms:show request.resource.postings.k_page_title /><cms:else /><cms:show k_page_title /></cms:if>"
			k_page_date="<cms:if request.resource.postings.k_page_date><cms:show request.resource.postings.k_page_date /><cms:else /><cms:date k_page_date format='Y-m-d H:i:s' /></cms:if>"
			_fields=request.resource.postings
		>
			<cms:if k_success>
				<cms:pages masterpage=request.api.template.name id="<cms:call 'post-or-get' 'id' />" show_future_entries='1'>
					<cms:set response.message="Edited '<cms:show request.api.template.title />' record." scope='global' />
					<cms:capture into='response.data' is_json='1'>{}</cms:capture>
					<cms:put var='response.data.k_page_title' value=k_page_title scope='global' />
					<cms:put var='response.data.k_page_name' value=k_page_name scope='global' />
					<cms:put var='response.data.k_page_date' value=k_page_date scope='global' />
					<cms:each request.api.template.fields as='field'>
						<cms:put var="response.data.<cms:show field.name />" value="<cms:get field.name />" scope='global' />
					</cms:each>
				</cms:pages>
			<cms:else />
				<cms:call 'api-abort' message="<cms:each k_error><cms:show item /><cms:if k_last_item='0'> | </cms:if></cms:each>" />
			</cms:if>
		</cms:db_persist>
	</cms:pages>



<!--
	:: v0.DELETE
	| For the delete route, we delete an existing record.
	| In the future, we may implement some sort of API-based confirmation step.
	| For now, we just delete the record, so be careful.
-->

<cms:else_if k_matched_route='v0.delete' />

	<cms:db_delete
		masterpage=request.api.template.name
		page_id="<cms:call 'post-or-get' 'id' />"
	/>
	<cms:pages masterpage=request.api.template.name id="0,<cms:call 'post-or-get' 'id' />">
		<cms:set response.message="Failed to delete '<cms:show request.api.template.title />' record... Not sure why that happened." scope='global' />
		<cms:no_results>
			<cms:set response.message="Deleted '<cms:show request.api.template.title />' record." scope='global' />
		</cms:no_results>
	</cms:pages>



<!--
	:: ROUTE NOT FOUND
	| If we get here, then we failed to match the request to any of the available API routes.
-->
<cms:else />

	<cms:set response.message='Failed to match request to available API routes.' scope='global' />

</cms:if>

<cms:call 'api-respond' />

<?php COUCH::invoke( K_IGNORE_CONTEXT );?>