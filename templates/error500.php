<?php if($this->get('error_details')) { ?>
<h1>Error</h1>
<p><?php out($this->get('exception_class')) ?> "<span class="text-danger"><?php out($this->get('error_details')->getMessage()) ?></span>"</p>
<p>Occurred in <?php out($this->get('error_details')->getFile().' line '.$this->get('error_details')->getLine()) ?></p>
<h2>Stack trace</h2>
<table class="table">
	<thead>
		<tr>
			<th>Function</th>
			<th>Arguments</th>
			<th>Location</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($this->get('error_details')->getTrace() as $stack_line) { ?>
		<?php if($stack_line['function'] != 'exception_error_handler') { ?>
		<tr>
			<td><?php out($stack_line['function'])?></td>
			<td>
				<?php if(!empty($stack_line['args'])) { ?>
				<ul>
					<?php foreach($stack_line['args'] as $arg) { ?>
					<li><?php out(print_r($arg, 1)) ?></li>
					<?php } ?>
				</ul>
				<?php } ?>
			</td>
			<td><?php out($stack_line['file'].' line '.$stack_line['line'])?></td>
		</tr>
		<?php } ?>
		<?php } ?>
	</tbody>
</table>
<?php } else { ?>
<h1>Oops! Something went wrong!</h1>
<p>Sorry, but it looks like something needs fixing on the system.  The problem has been automatically reported to the administrators, but if you wish, you can also <a href="mailto:<?php out($this->get('admin_address'))?>?subject=<?php out('SSL Cert Authority error number '.$this->get('error_number'), ESC_URL_ALL)?>">provide additional information</a> about what you were doing that may have triggered the error.</p>
<?php } ?>
