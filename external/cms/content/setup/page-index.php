<?php $site->partial('header'); ?>

	<section>
		<div class="inner boxfix-vert">
			<div class="margins">
				<div class="center-col">
					<div class="metabox">
						<div class="metabox-header">Setup wizard</div>
						<div class="metabox-body">
							<div class="the-content">
								<h1>Welcome!</h1>
								<p>This wizard will guide you through the setup process of your Hummingbird CMS instance.</p>
								<?php if (! $site->getDatabase() ): ?>
									<p>First, you will need to manually configure the database connection of yout Hummingbird Lite instance, check <a href="https://docs.vecode.net/hummingbird-v3/tutorials/database" target="_blank">this link</a> for more information on how to do so.</p>
									<p>Once configured, refresh this page to continue.</p>
									<a href="<?php $site->urlTo('/cms/utils/setup?r='.time(), true); ?>" class="button button-primary">Refresh page</a>
								<?php else: ?>
									<p>Great! Your database connection is properly configured.</p>
									<p>Please click the button below to continue.</p>
									<div class="text-center">
										<a href="<?php $site->urlTo('/cms/utils/setup/install', true); ?>" class="button button-primary">Continue</a>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

<?php $site->partial('footer'); ?>