		</div>
		<p class="hc-foot"><?php echo hc_h($page_foot ?? 'Hendricks Coffee · Zeiten in MEZ · Umsatz zählt Herstellung und fertig'); ?></p>
	</main>
	<script>
		document.querySelectorAll('.nav-toggle').forEach(function (button) {
			button.addEventListener('click', function () {
				document.body.classList.toggle('nav-open');
			});
		});
	</script>
</body>
</html>
