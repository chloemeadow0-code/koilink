<?php
get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article class="content-page">
		<h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
		<div class="entry"><?php the_excerpt(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
