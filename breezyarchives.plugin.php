<?php
require_once 'breezyarchiveshandler.php';

class BreezyArchives extends Plugin
{
	private $config = array();
	private $class_name = '';
	private $cache_name = '';
	private $default_options = array (
		/* Chronology */
		'chronology_title' => 'Chronology',
		'month_format' => 'M',
		'show_monthly_post_count' => TRUE,
		/* Taxonomy */
		'taxonomy_title' => 'Taxonomy',
		'show_tag_post_count' => TRUE,
		'excluded_tags' => array(),
		/* Pagination */
		'posts_per_page' => 15,
		'next_page_text' => 'Older →',
		'prev_page_text' => '← Newer',
		/* General */
		'show_newest_first' => TRUE,
		'show_comment_count' => TRUE
	);

	public function info()
	{
		return array(
			'name' => 'Breezy Archives',
			'version' => '0.6-0.2-pre',
			'url' => 'http://code.google.com/p/bcse/wiki/BreezyArchives',
			'author' => 'Joel Lee',
			'authorurl' => 'http://blog.bcse.info/',
			'license' => 'Apache License 2.0',
			'description' => 'An archives plugin which mimics ‘Live Archives’ on WordPress. When JavaScript is not available, it will graceful degrade to a ‘Clean Archives’.',
			'copyright' => '2008'
		);
	}

	/**
	 * On plugin activation, set the default options
	 */
	public function action_plugin_activation($file)
	{
		if (realpath($file) === __FILE__) {
			$this->class_name = strtolower(get_class($this));
			$this->cache_name = Site::get_url('host') . ':' . $this->class_name;
			foreach ($this->default_options as $name => $value) {
				$current_value = Options::get($this->class_name . '__' . $name);
				if (is_null($current_value)) {
					Options::set($this->class_name . '__' . $name, $value);
				}
			}
		}
	}

	/**
	 * On plugin init, add the template included with this plugin to the available templates in the theme
	 */
	public function action_init()
	{
		$this->class_name = strtolower(get_class($this));
		foreach ($this->default_options as $name => $value) {
			$this->config[$name] = Options::get($this->class_name . '__' . $name);
		}
		$this->load_text_domain($this->class_name);
		$this->add_template('breezyarchives', dirname(__FILE__) . '/breezyarchives.php');
		$this->add_template('breezyarchives_chrono', dirname(__FILE__) . '/breezyarchives_chrono.php');
		$this->add_template('breezyarchives_month', dirname(__FILE__) . '/breezyarchives_month.php');
		$this->add_template('breezyarchives_tags', dirname(__FILE__) . '/breezyarchives_tags.php');
		$this->add_template('breezyarchives_tag', dirname(__FILE__) . '/breezyarchives_tag.php');
		$this->add_template('breezyarchives_js', dirname(__FILE__) . '/breezyarchives.js');
	}

	/**
	 * Add update beacon support
	 **/
	public function action_update_check()
	{
	 	Update::add('Breezy Archives', '2f6d8d49-1e93-4c46-924f-af8a351af10a', $this->info->version);
	}

	/**
	 * Add actions to the plugin page for this plugin
	 * @param array $actions An array of actions that apply to this plugin
	 * @param string $plugin_id The string id of a plugin, generated by the system
	 * @return array The array of actions to attach to the specified $plugin_id
	 **/
	public function filter_plugin_config($actions, $plugin_id)
	{
		if ($plugin_id === $this->plugin_id()) {
			$actions[] = _t('Configure', $this->class_name);
		}

		return $actions;
	}

	/**
	 * Respond to the user selecting an action on the plugin page
	 * @param string $plugin_id The string id of the acted-upon plugin
	 * @param string $action The action string supplied via the filter_plugin_config hook
	 **/
	public function action_plugin_ui($plugin_id, $action)
	{
		if ($plugin_id === $this->plugin_id()) {
			switch ($action) {
				case _t('Configure', $this->class_name):
					$ui = new FormUI($this->class_name);

					$ui->append('fieldset', 'chronology', _t('Chronology', $this->class_name));

					$ui->chronology->append('text', 'chronology_title', 'option:' . $this->class_name . '__chronology_title', _t('Title for Chronology Archives', $this->class_name));
					$ui->chronology->chronology_title->add_validator('validate_required');

					$ui->chronology->append(
						'select',
						'month_format',
						'option:' . $this->class_name . '__month_format',
						_t('Month Format', $this->class_name),
						array(
							'F' => _t('Full name (January – December)', $this->class_name),
							'%B' => sprintf(_t('Full name according to the current system locale (%1$s – %2$s)', $this->class_name), strftime('%B', mktime(0,0,0,1,1)), strftime('%B', mktime(0,0,0,12,1))),
							'M' => _t('Abbreviation (Jan – Dec)', $this->class_name),
							'%b' => sprintf(_t('Abbreviation according to the current system locale (%1$s – %2$s)', $this->class_name), strftime('%b', mktime(0,0,0,1,1)), strftime('%b', mktime(0,0,0,12,1))),
							'm' => _t('Number with leading zero (01 – 12)', $this->class_name),
							'n' => _t('Number without leading zero (1 – 12)', $this->class_name)
						)
					);

					$ui->chronology->append('checkbox', 'show_monthly_post_count', 'option:' . $this->class_name . '__show_monthly_post_count', _t('Show Monthly Posts Count', $this->class_name));

					$ui->append('fieldset', 'taxonomy', _t('Taxonomy', $this->class_name));

					$ui->taxonomy->append('text', 'taxonomy_title', 'option:' . $this->class_name . '__taxonomy_title', _t('Title for Taxonomy Archives', $this->class_name));
					$ui->taxonomy->taxonomy_title->add_validator('validate_required');

					$ui->taxonomy->append('checkbox', 'show_tag_post_count', 'option:' . $this->class_name . '__show_tag_post_count', _t('Show Tagged Posts Count', $this->class_name));

					$ui->taxonomy->append('textmulti', 'excluded_tags', 'option:' . $this->class_name . '__excluded_tags', _t('Excluded Tags', $this->class_name));

					$ui->append('fieldset', 'pagination', _t('Pagination', $this->class_name));

					$ui->pagination->append('text', 'posts_per_page', 'option:' . $this->class_name . '__posts_per_page', _t('&#8470; of Posts per Page', $this->class_name));
					$ui->pagination->posts_per_page->add_validator('validate_uint');
					$ui->pagination->posts_per_page->add_validator('validate_required');

					$ui->pagination->append('text', 'next_page_text', 'option:' . $this->class_name . '__next_page_text', _t('Next Page Link Text', $this->class_name));
					$ui->pagination->next_page_text->add_validator('validate_required');

					$ui->pagination->append('text', 'prev_page_text', 'option:' . $this->class_name . '__prev_page_text', _t('Previous Page Link Text', $this->class_name));
					$ui->pagination->prev_page_text->add_validator('validate_required');

					$ui->append('fieldset', 'general', _t('General', $this->class_name));

					$ui->general->append('checkbox', 'show_newest_first', 'option:' . $this->class_name . '__show_newest_first', _t('Show Newest First', $this->class_name));

					$ui->general->append('checkbox', 'show_comment_count', 'option:' . $this->class_name . '__show_comment_count', _t('Show &#8470; of Comments', $this->class_name));
/*
					$ui->taxonomy->append(
						'select',
						'displayed_tags',
						'option:' . $this->class_name . '__displayed_tags',
						_t('Displayed Tags', $this->class_name),
						array(
							'all' => 'Show all tags',
							'fave' => 'Show the first N most-used tags',
							'big' => 'Show tags with more than N posts'
						)
					);

					$ui->general->append('text', 'loading_content', 'option:' . $this->class_name . '__loading_content', _t('Loading Content', $this->class_name));
*/
					// When the form is successfully completed, call $this->updated_config()
					$ui->append('submit', 'save', _t('Save', $this->class_name));
					$ui->set_option('success_message', _t('Options saved', $this->class_name));
					$ui->out();
					break;
			}
		}
	}

	public function validate_uint($value)
	{
		if (!ctype_digit($value) || strstr($value, '.') || $value < 0) {
			return array(_t('This field must be positive integer.', $this->class_name));
		}
		return array();
	}

	/**
	 * Returns true if plugin config form values defined in action_plugin_ui should be stored in options by Habari
	 * @return bool True if options should be stored
	 **/
	public function updated_config($ui)
	{
		return true;
	}

	public function filter_rewrite_rules($rules)
	{
		$rules[] = new RewriteRule(array(
			'name' => 'display_breezyarchives_by_month',
			'parse_regex' => '%^(?P<class_name>' . $this->class_name . ')/(?P<year>[1,2]{1}[\d]{3})/(?P<month>[\d]{2})(?:/page/(?P<page>\d+))?/?$%i',
			'build_str' => '{$class_name}/{$year}/{$month}(/page/{$page})',
			'handler' => 'BreezyArchivesHandler',
			'action' => 'display_breezyarchives_by_month',
			'rule_class' => RewriteRule::RULE_PLUGIN,
			'is_active' => 1,
			'description' => 'Displays Breezy Archives for a specific month.'
		));
		$rules[] = new RewriteRule(array(
			'name' => 'display_breezyarchives_by_tag',
			'parse_regex' => '%^(?P<class_name>' . $this->class_name . ')/tag/(?P<tag_slug>[^/]*)(?:/page/(?P<page>\d+))?/?$%i',
			'build_str' => '{$class_name}/tag/{$tag_slug}(/page/{$page})',
			'handler' => 'BreezyArchivesHandler',
			'action' => 'display_breezyarchives_by_tag',
			'rule_class' => RewriteRule::RULE_PLUGIN,
			'is_active' => 1,
			'description' => 'Displays Breezy Archives for a specific tag.'
		));
		$rules[] = new RewriteRule(array(
			'name' => 'display_breezyarchives_js',
			'parse_regex' => '%^scripts/jquery.(?P<class_name>' . $this->class_name . ')_(?P<config>[0-9a-f]{32}).js$%i',
			'build_str' =>  'scripts/jquery.{$class_name}_{$config}.js',
			'handler' => 'BreezyArchivesHandler',
			'action' => 'display_breezyarchives_js',
			'rule_class' => RewriteRule::RULE_PLUGIN,
			'is_active' => 1,
			'description' => 'Displays Breezy Archives JavaScript content.'
		));
		return $rules;
	}

	public function theme_header($theme)
	{
		if ($theme->template_exists($this->class_name . '.css')) {
			$css_path = $theme->get_url(TRUE) . $this->class_name . '.css';
		} else {
			$css_path = $this->get_url(TRUE) . $this->class_name . '.css';
		}
		Stack::add('template_stylesheet', array($css_path, 'screen'), 'breezyarchives');
	}

	public function theme_breezyarchives($theme)
	{
		Stack::add('template_footer_javascript', 'http://ajax.googleapis.com/ajax/libs/jquery/1.2.6/jquery.min.js', 'jquery');
		Stack::add('template_footer_javascript', Site::get_url('scripts') . '/jquery.spinner.js', 'jquery.spinner');
		Stack::add('template_footer_javascript', URL::get('display_breezyarchives_js', array('class_name' => $this->class_name, 'config' => md5(serialize($this->config)))), 'jquery.breezyarchives');

		if (Cache::has($this->cache_name)) {
			return Cache::get($this->cache_name);
		} else {
			$theme->chronology_title = $this->config['chronology_title'];
			$theme->taxonomy_title = $this->config['taxonomy_title'];
			$ret = $theme->fetch('breezyarchives');
			Cache::set($this->cache_name, $ret);
			return $ret;
		}
	}

	public function theme_chronology_archives($theme)
	{
		$sql =
		   'SELECT YEAR(FROM_UNIXTIME(pubdate)) AS year, MONTH(FROM_UNIXTIME(pubdate)) AS month, COUNT(id) AS count
			FROM {posts}
			WHERE content_type = ' . Post::type('entry') . '
			  AND status = ' . Post::status('published') . '
			GROUP BY year, month
			ORDER BY year DESC, month DESC';
		$months = DB::get_results($sql);

		$years = array();
		$year_first = $months[0]->year;
		$year_last = end($months)->year;
		$years = array_fill_keys(range($year_first, $year_last), array());

		if ($this->config['show_newest_first']) {
			foreach ($years as &$y) {
				$y = array_fill_keys(array('12', '11', '10', '09', '08', '07', '06', '05', '04', '03', '02', '01'), 0);
			}
		} else {
			foreach ($years as &$y) {
				$y = array_fill_keys(array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'), 0);
			}
		}

		foreach ($months as $m) {
			$years[$m->year][str_pad($m->month, 2, '0', STR_PAD_LEFT)] = $m->count;
		}

		$theme->years = $years;
		$theme->show_monthly_post_count = $this->config['show_monthly_post_count'];
		$theme->month_format = $this->config['month_format'];

		return $theme->fetch('breezyarchives_chrono');
	}

	public function theme_taxonomy_archives($theme)
	{
		$where = '';
		if (count($this->config['excluded_tags']) > 0) {
			$where = 'WHERE t.tag_slug NOT IN ("' . implode('","', $this->config['excluded_tags']) . '")';
		}
		$sql = sprintf(
		   'SELECT t.id AS id, t.tag_text AS tag, t.tag_slug AS slug, COUNT(tp.tag_id) AS count
			FROM {tags} t LEFT JOIN {tag2post} tp ON t.id=tp.tag_id
			%1$s
			GROUP BY id, tag, slug
			HAVING count > 0
			ORDER BY tag ASC', $where);
		$theme->tags = DB::get_results($sql);
		$theme->show_tag_post_count = $this->config['show_tag_post_count'];
		return $theme->fetch('breezyarchives_tags');
	}

	public function action_post_update_status($post, $old_value, $new_value)
	{
		if ((Post::status_name($old_value) == 'published' && Post::status_name($new_value) != 'published') ||
			  (Post::status_name($old_value) != 'published' && Post::status_name($new_value) == 'published')) {
			Cache::expire($this->cache_name);
		}
	}

	public function action_post_update_slug($post, $old_value, $new_value)
	{
		if (Post::status_name($post->status) == 'published') {
			Cache::expire($this->cache_name);
		}
	}

	public function action_post_update_title($post, $old_value, $new_value)
	{
		if (Post::status_name($post->status) == 'published') {
			Cache::expire($this->cache_name);
		}
	}

	public function action_post_delete_after($post)
	{
		if (Post::status_name($post->status) == 'published') {
			Cache::expire($this->cache_name);
		}
	}
}
?>
