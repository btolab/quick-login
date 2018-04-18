<?php
namespace Layered\QuickLogin;

use Layered\QuickLogin\Provider;

class Admin {

	public static function start() {
		return new static;
	}

	protected function __construct() {
		add_action('admin_enqueue_scripts', [$this, 'assets']);
		add_action('admin_init', [$this, 'actions']);
		add_action('admin_menu', [$this, 'menu']);
		add_action('admin_notices', [$this, 'notices']);
		add_filter('plugin_action_links_quick-login/quick-login.php', [$this, 'actionLinks']);

		$this->providers = apply_filters('quick_login_providers', []);
	}

	public function assets() {
		wp_enqueue_script('quick-login-admin', plugins_url('assets/quick-login-admin.js', dirname(__FILE__)), ['jquery'], '1.0', true);
		wp_enqueue_style('quick-login', plugins_url('assets/quick-login.css', dirname(__FILE__)));
		wp_enqueue_style('quick-login-admin', plugins_url('assets/quick-login-admin.css', dirname(__FILE__)));
	}

	public function actions() {

		if (isset($_POST['quick-login-provider-settings-save'])) {
			$provider = $this->providers[$_REQUEST['quick-login-provider-settings']];
			$options = [];

			if ($provider->getOption('status') === 'needs-setup') {
				$options['status'] = 'enabled';
			}

			foreach ($provider->getUserSettings() as $key => $setting) {
				$options[$key] = $_POST[$key];
			}

			$provider->updateOptions($options);
			$message = sprintf(__('Settings for %s are updated', 'quick-login'), $provider->getLabel());

			wp_redirect(admin_url('options-general.php?page=quick-login-options&quick-login-alert=' . urlencode($message)));
			exit;
		}

		if (isset($_REQUEST['quick-login-provider-enable'])) {
			$provider = $this->providers[$_REQUEST['quick-login-provider-enable']];
			$provider->updateOptions([
				'status'	=>	'enabled'
			]);
			$message = sprintf(__('%s is enabled!', 'quick-login'), $provider->getLabel());

			wp_redirect(admin_url('options-general.php?page=quick-login-options&quick-login-alert=' . urlencode($message)));
			exit;
		}

		if (isset($_REQUEST['quick-login-provider-disable'])) {
			$provider = $this->providers[$_REQUEST['quick-login-provider-disable']];
			$provider->updateOptions([
				'status'	=>	'disabled'
			]);
			$message = sprintf(__('%s is disabled', 'quick-login'), $provider->getLabel());

			wp_redirect(admin_url('options-general.php?page=quick-login-options&quick-login-alert=' . urlencode($message) . '&alert-type=warning'));
			exit;
		}

		if (isset($_POST['quick-login-settings'])) {
			$options = array_merge(get_option('quick-login'), [
				'login-form'		=>	$_POST['quick-login-login-form'],
				'login-style'		=>	$_POST['quick-login-login-style'],
				'register-form'		=>	$_POST['quick-login-register-form'],
				'register-style'	=>	$_POST['quick-login-register-style'],
				'comment-form'		=>	$_POST['quick-login-comment-form'],
				'comment-style'		=>	$_POST['quick-login-comment-style']
			]);

			update_option('quick-login', $options);
			$message = __('Quick Login options are updated', 'quick-login');

			wp_redirect(admin_url('options-general.php?page=quick-login-options&quick-login-alert=' . urlencode($message)));
			exit;
		}

	}

	public function menu() {
		add_options_page(__('Quick Login Options', 'quick-login'), __('Quick Login', 'quick-login'), 'manage_options', 'quick-login-options', [$this, 'page']);
	}

	public function notices() {
		$notices = [];
		$providers = apply_filters('quick_login_providers', []);
		$providers = array_filter($providers, function(Provider $provider) {
			return $provider->getOption('status') === 'enabled';
		});

		if (!count($providers)) {
			$notices[] = [
				'type'		=>	'warning',
				'message'	=>	sprintf(__('<strong>Quick Login</strong> plugin is active, but no login providers are enabled. <a href="%s">Enable providers now</a> and let visitors log in with Facebook, Twitter or Google', 'quick-login'), admin_url('options-general.php?page=quick-login-options'))
			];
		}

		if (isset($_REQUEST['quick-login-alert'])) {
			$notices[] = [
				'type'			=>	isset($_REQUEST['alert-type']) ? $_REQUEST['alert-type'] : 'success',
				'message'		=>	$_REQUEST['quick-login-alert'],
				'dismissable'	=>	true
			];
		}

		foreach ($notices as $notice) {
			?>
			<div class="notice notice-<?php echo esc_attr($notice['type']) ?> <?php if (isset($notice['dismissable']) && $notice['dismissable'] === true) echo 'is-dismissible' ?>">
				<p><?php echo wp_kses($notice['message'], ['a' => ['href' => [], 'title' => []], 'strong' => []]) ?></p>
			</div>
			<?php
		}
	}

	public function actionLinks(array $links) {
		return array_merge([
			'settings'	=>	'<a href="' . menu_page_url('quick-login-options', false) . '">' . __('Settings', 'quick-login') . '</a>'
		], $links);
	}

	public function page() {
		$statuses = [
			'needs-setup'	=>	__('Needs setup', 'quick-login'),
			'disabled'		=>	__('Disabled', 'quick-login'),
			'enabled'		=>	__('Enabled', 'quick-login')
		];
		$options = get_option('quick-login');
		?>

		<div class="wrap about-wrap quick-login-wrap">
			<h1><?php _e('Quick Login', 'quick-login') ?></h1>

			<?php if (isset($_REQUEST['quick-login-provider-settings']) && isset($this->providers[$_REQUEST['quick-login-provider-settings']])) : ?>
				<?php
				$provider = $this->providers[$_REQUEST['quick-login-provider-settings']];
				?>

				<h3><?php printf(__('Set up %s', 'quick-login'), $provider->getLabel()) ?></h3>

				<?php $provider->instructions() ?>

				<form method="post">
					<table class="form-table">
						<tbody>
							<?php foreach ($provider->getUserSettings() as $key => $setting) : ?>
								<tr>
									<th scope="row"><label for="<?php echo esc_attr($key) ?>"><?php echo $setting['name'] ?></label></th>
									<td>
										<input name="<?php echo esc_attr($key) ?>" type="<?php echo esc_attr($setting['type']) ?>" id="<?php echo esc_attr($key) ?>" <?php if ($setting['required']) echo 'required'  ?> value="<?php echo $provider->getOption($key, $setting['default']) ?>" class="regular-text">
									</td>
								</tr>
							<?php endforeach ?>
						</tbody>
						<tfoot>
							<tr>
								<td>
									<p><a href="<?php echo admin_url('options-general.php?page=quick-login-options') ?>" class="button button-secondary"><?php _e('Cancel', 'quick-login') ?></a></p>
								</td>
								<td>
									<p class="regular-text text-right"><input type="submit" name="quick-login-provider-settings-save" id="submit" class="button button-primary" value="<?php _e('Save settings', 'quick-login') ?>"></p>
								</td>
							</tr>
						</tfoot>
					</table>
				</form>

			<?php else : ?>

				<p class="about-text"><?php _e('Let your visitors log in quicker with their existing accounts!', 'if-menu') ?></p>

				<h3><span>1.</span> <?php _e('Enable login providers', 'quick-login') ?></h3>

				<div class="quick-login-admin-providers">
					<?php foreach ($this->providers as $provider) : ?>
						<div class="quick-login-admin-provider" style="--quick-login-color: <?php echo $provider->getColor() ?>">
							<div class="quick-login-admin-provider-name">
								<?php echo $provider->getIcon() ?>
								<p><?php echo $provider->getLabel() ?></p>

								<?php if ($provider->getOption('status') !== 'needs-setup') : ?>
									<a href="<?php echo admin_url('options-general.php?page=quick-login-options&quick-login-provider-settings=' . $provider->getId()) ?>"><?php _e('Settings', 'quick-login') ?></a>
								<?php endif ?>
							</div>
							<div class="quick-login-admin-provider-actions">
								<?php if ($provider->getOption('status') === 'needs-setup') : ?>
									<a href="<?php echo admin_url('options-general.php?page=quick-login-options&quick-login-provider-settings=' . $provider->getId()) ?>" class="quick-login-admin-provider-action"><?php _e('Setup', 'quick-login') ?></a>
								<?php elseif ($provider->getOption('status') === 'disabled') : ?>
									<a href="<?php echo admin_url('options-general.php?page=quick-login-options&quick-login-provider-enable=' . $provider->getId()) ?>" class="quick-login-admin-provider-action"><?php _e('Enable', 'quick-login') ?></a>
								<?php elseif ($provider->getOption('status') === 'enabled') : ?>
									<a href="<?php echo admin_url('options-general.php?page=quick-login-options&quick-login-provider-disable=' . $provider->getId()) ?>" class="quick-login-admin-provider-action"><?php _e('Disable', 'quick-login') ?></a>
								<?php endif ?>

								<span class="quick-login-admin-provider-status quick-login-status-<?php echo $provider->getOption('status') ?>"></span>
								<?php echo $statuses[$provider->getOption('status')] ?>
							</div>
						</div>
					<?php endforeach ?>
				</div>

				<div class="quick-login-clear"></div>
				<h3><span>2.</span> <?php _e('Where should the logins be displayed?', 'quick-login') ?></h3>

				<form method="post">
					<table class="form-table">
						<tbody>
							<tr class="quick-login-form-preview">
								<th>
									<label for="quick-login-login-form"><?php _e('Login form', 'quick-login') ?></label>
									<p class="description"><?php _e('WP & WooCommerce', 'quick-login') ?></p>
								</th>
								<td>
									<fieldset>
										<legend><?php _e('Position', 'quick-login') ?></legend>
										<label><input type="radio" name="quick-login-login-form" class="quick-login-position" value="top" <?php checked('top', $options['login-form']) ?>> <?php _e('Top', 'quick-login') ?></label><br>
										<label><input type="radio" name="quick-login-login-form" class="quick-login-position" value="bottom" <?php checked('bottom', $options['login-form']) ?>> <?php _e('Bottom', 'quick-login') ?></label><br>
										<label><input type="radio" name="quick-login-login-form" class="quick-login-position" value="no" <?php checked('no', $options['login-form']) ?>> <?php _e('Hidden', 'quick-login') ?></label>
									</fieldset>
								</td>
								<td>
									<fieldset>
										<legend><?php _e('Button style', 'quick-login') ?></legend>
										<label><input type="radio" name="quick-login-login-style" class="quick-login-style" value="button" <?php checked('button', $options['login-style']) ?>> <?php _e('Buttons', 'quick-login') ?></label><br>
										<label><input type="radio" name="quick-login-login-style" class="quick-login-style" value="icon" <?php checked('icon', $options['login-style']) ?>> <?php _e('Icons', 'quick-login') ?></label><br>
									</fieldset>
								</td>
								<td>
									<div class="preview-login preview-position-<?php echo $options['login-form'] ?> preview-style-<?php echo $options['login-style'] ?>">
										<div class="quick-login-buttons on-top">
											<div class="quick-login-button" style="--quick-login-color: #3B5998"></div>
											<div class="quick-login-button" style="--quick-login-color: #dc4e41"></div>
										</div>

										<div class="quick-login-icons on-top">
											<div class="quick-login-icon" style="--quick-login-color: #3B5998"></div>
											<div class="quick-login-icon" style="--quick-login-color: #dc4e41"></div>
											<div class="quick-login-icon" style="--quick-login-color: #4ab3f4"></div>
											<div class="quick-login-icon" style="--quick-login-color: #21759B"></div>
										</div>

										<div class="quick-login-separator on-top"><span><?php _e('or', 'quick-login') ?></span></div>
										<div class="preview-field"></div>
										<div class="preview-field"></div>
										<div class="preview-button"></div>
										<div class="quick-login-separator on-bottom"><span><?php _e('or', 'quick-login') ?></span></div>

										<div class="quick-login-buttons on-bottom">
											<div class="quick-login-button" style="--quick-login-color: #3B5998"></div>
											<div class="quick-login-button" style="--quick-login-color: #dc4e41"></div>
										</div>

										<div class="quick-login-icons on-bottom">
											<div class="quick-login-icon" style="--quick-login-color: #3B5998"></div>
											<div class="quick-login-icon" style="--quick-login-color: #dc4e41"></div>
											<div class="quick-login-icon" style="--quick-login-color: #4ab3f4"></div>
											<div class="quick-login-icon" style="--quick-login-color: #21759B"></div>
										</div>
									</div>
								</td>
							</tr>

							<tr class="quick-login-form-preview">
								<th scope="row"><label for="quick-login-register-form">
									<?php _e('Register form', 'quick-login') ?></label>
									<p class="description"><?php _e('WP & WooCommerce', 'quick-login') ?></p>
								</th>
								<td>
									<fieldset>
										<legend><?php _e('Position', 'quick-login') ?></legend>
										<label><input type="radio" name="quick-login-register-form" class="quick-login-position" value="top" <?php checked('top', $options['register-form']) ?>> <?php _e('Top', 'quick-login') ?></label><br>
										<label><input type="radio" name="quick-login-register-form" class="quick-login-position" value="bottom" <?php checked('bottom', $options['register-form']) ?>> <?php _e('Bottom', 'quick-login') ?></label><br>
										<label><input type="radio" name="quick-login-register-form" class="quick-login-position" value="no" <?php checked('no', $options['register-form']) ?>> <?php _e('Hidden', 'quick-login') ?></label>
									</fieldset>
								</td>
								<td>
									<fieldset>
										<legend><?php _e('Button style', 'quick-login') ?></legend>
										<label><input type="radio" name="quick-login-register-style" class="quick-login-style" value="button" <?php checked('button', $options['register-style']) ?>> <?php _e('Buttons', 'quick-login') ?></label><br>
										<label><input type="radio" name="quick-login-register-style" class="quick-login-style" value="icon" <?php checked('icon', $options['register-style']) ?>> <?php _e('Icons', 'quick-login') ?></label><br>
									</fieldset>
								</td>
								<td>
									<div class="preview-login preview-position-<?php echo $options['register-form'] ?> preview-style-<?php echo $options['register-style'] ?>">
										<div class="quick-login-buttons on-top">
											<div class="quick-login-button" style="--quick-login-color: #3B5998"></div>
											<div class="quick-login-button" style="--quick-login-color: #dc4e41"></div>
										</div>

										<div class="quick-login-icons on-top">
											<div class="quick-login-icon" style="--quick-login-color: #3B5998"></div>
											<div class="quick-login-icon" style="--quick-login-color: #dc4e41"></div>
											<div class="quick-login-icon" style="--quick-login-color: #4ab3f4"></div>
											<div class="quick-login-icon" style="--quick-login-color: #21759B"></div>
										</div>

										<div class="quick-login-separator on-top"><span><?php _e('or', 'quick-login') ?></span></div>
										<div class="preview-field"></div>
										<div class="preview-field"></div>
										<div class="preview-field"></div>
										<div class="preview-button"></div>
										<div class="quick-login-separator on-bottom"><span><?php _e('or', 'quick-login') ?></span></div>

										<div class="quick-login-buttons on-bottom">
											<div class="quick-login-button" style="--quick-login-color: #3B5998"></div>
											<div class="quick-login-button" style="--quick-login-color: #dc4e41"></div>
										</div>

										<div class="quick-login-icons on-bottom">
											<div class="quick-login-icon" style="--quick-login-color: #3B5998"></div>
											<div class="quick-login-icon" style="--quick-login-color: #dc4e41"></div>
											<div class="quick-login-icon" style="--quick-login-color: #4ab3f4"></div>
											<div class="quick-login-icon" style="--quick-login-color: #21759B"></div>
										</div>
									</div>
								</td>
							</tr>

							<tr class="quick-login-form-preview">
								<th scope="row"><label for="quick-login-comment-form"><?php _e('Comment section', 'quick-login') ?></label></th>
								<td>
									<fieldset>
										<legend><?php _e('Position', 'quick-login') ?></legend>
										<label><input type="radio" name="quick-login-comment-form" class="quick-login-position" value="top" <?php checked('top', $options['comment-form']) ?>> <?php _e('Top', 'quick-login') ?></label><br>
										<label><input type="radio" name="quick-login-comment-form" class="quick-login-position" value="no" <?php checked('no', $options['comment-form']) ?>> <?php _e('Hidden', 'quick-login') ?></label>
									</fieldset>
								</td>
								<td>
									<fieldset>
										<legend><?php _e('Button style', 'quick-login') ?></legend>
										<label><input type="radio" name="quick-login-comment-style" class="quick-login-style" value="button" <?php checked('button', $options['comment-style']) ?>> <?php _e('Buttons', 'quick-login') ?></label><br>
										<label><input type="radio" name="quick-login-comment-style" class="quick-login-style" value="icon" <?php checked('icon', $options['comment-style']) ?>> <?php _e('Icons', 'quick-login') ?></label><br>
									</fieldset>
								</td>
								<td>
									<div class="preview-login preview-position-<?php echo $options['comment-form'] ?> preview-style-<?php echo $options['comment-style'] ?>">
										<div class="quick-login-buttons on-top">
											<div class="quick-login-button" style="--quick-login-color: #3B5998"></div>
											<div class="quick-login-button" style="--quick-login-color: #dc4e41"></div>
										</div>

										<div class="quick-login-icons on-top">
											<div class="quick-login-icon" style="--quick-login-color: #3B5998"></div>
											<div class="quick-login-icon" style="--quick-login-color: #dc4e41"></div>
											<div class="quick-login-icon" style="--quick-login-color: #4ab3f4"></div>
											<div class="quick-login-icon" style="--quick-login-color: #21759B"></div>
											<br><br>
										</div>

										<div class="preview-field"></div>
										<div class="preview-field"></div>
										<div class="preview-field field-double"></div>
										<div class="preview-button"></div>
									</div>
								</td>
							</tr>

						</tbody>
					</table>

					<p class="submit">
						<input type="submit" name="quick-login-settings" class="button button-primary" value="<?php _e('Save Changes', 'quick-login') ?>">
					</p>
				</form>

				<div class="quick-login-clear"></div>
				<h3><span>3.</span> <?php _e('Embed login buttons on more pages', 'quick-login') ?></h3>

				<table class="form-table" width="100%">
					<tbody>
						<tr>
							<th>
								<label for="quick-login-login-form"><?php _e('Shortcode', 'quick-login') ?></label>
								<p class="description"><?php _e('Add login buttons in pages, articles or widgets', 'quick-login') ?></p>
							</th>
							<td>
								<textarea class="code large-text" cols="30" rows="5">[quick-login style="icon" separator="bottom" heading="Login with"]</textarea>
							</td>
							<td>
								<fieldset>
									<legend><?php _e('Attributes', 'quick-login') ?></legend>
									<label><strong>style</strong> - <code>button</code> or <code>link</code></label><br>
									<label><strong>separator</strong> - <code>no</code>, <code>top</code> or <code>bottom</code></label><br>
									<label><strong>heading</strong> - <?php _e('custom heading text, ex:', 'quick-login') ?> <code>Sign in here:</code></label>
								</fieldset>
							</td>
						</tr>

						<tr>
							<th>
								<label for="quick-login-login-form"><?php _e('Link', 'quick-login') ?></label>
								<p class="description"><?php _e('Point images or buttons at this link for login') ?></p>
							</th>
							<td>
								<code><?php echo site_url('/wp-login.php') ?>?quick-login=<u>google</u></code>
							</td>
							<td>
								<fieldset>
									<legend><?php _e('Parameters', 'quick-login') ?></legend>
									<label><strong>quick-login</strong> - <code>google</code>, <code>facebook</code> or another enabled provider</label><br>
									<label><strong>redirect_to</strong> - post login redirect URL, default is site homepage</label><br>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>

			<?php endif ?>

		</div>
		<?php
	}

}
