<?php $site->partial('header'); ?>

	<section>
		<div class="inner boxfix-vert">
			<div class="margins">
				<div class="center-col">
					<div class="metabox">
						<div class="metabox-header">Setup wizard</div>
						<div class="metabox-body">
							<div class="the-content">
								<h1>Configuration</h1>
								<p>The database tables have been created, now we will configure your Hummingbird CMS instance.</p>
								<p>Please fill all the fields and click the button below to continue.</p>
								<form action="" method="post" data-submit="validate">
									<div class="form-field">
										<h3>Administrator user</h3>
										<?php if (Users::count() == 0): ?>
											<div class="form-group">
												<label for="login" class="control-label">Login<span class="required">*</span></label>
												<input type="text" name="login" id="login" class="form-control input-block" data-validate="required">
											</div>
											<div class="form-group">
												<label for="email" class="control-label">Email<span class="required">*</span></label>
												<input type="text" name="email" id="email" class="form-control input-block" data-validate="required|email">
											</div>
											<div class="form-group">
												<label for="nicename" class="control-label">Display name<span class="required">*</span></label>
												<input type="text" name="nicename" id="nicename" class="form-control input-block" data-validate="required">
											</div>
											<div class="form-group">
												<label for="password" class="control-label">Password<span class="required">*</span></label>
												<input type="password" name="password" id="password" class="form-control input-block" data-validate="required">
											</div>
											<div class="form-group">
												<label for="confirm" class="control-label">Password confirm<span class="required">*</span></label>
												<input type="password" name="confirm" id="confirm" class="form-control input-block" data-validate="confirm" data-param="#password">
											</div>
										<?php else: ?>
											<div><em>The administrator user has been already created</em></div>
										<?php endif; ?>
									</div>
									<br>
									<div class="text-center">
										<button type="submit" class="button button-primary">Save configuration</button>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

<?php $site->partial('footer'); ?>