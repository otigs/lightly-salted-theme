<?php

use Timber\Timber;

$context = Timber::context();
Timber::render('templates/single-blog_archive.twig', $context);
