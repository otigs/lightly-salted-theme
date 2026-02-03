<?php

use Timber\Timber;

$context = Timber::context();
Timber::render('templates/single-blog_topic.twig', $context);
