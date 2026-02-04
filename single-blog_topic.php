<?php

use Timber\Timber;

$context = Timber::context();
$term = null;

if (is_tax('topic')) {
    $term = get_queried_object();
    $context['term'] = Timber::get_term($term);
}

if (!$term && isset($context['post'])) {
    $topicName = $context['post']->meta('topic_name');
    if (!empty($topicName)) {
        $term = get_term_by('name', $topicName, 'topic');
        if ($term) {
            $context['term'] = Timber::get_term($term);
        }
    }
}

if ($term) {
    $context['topic_posts'] = Timber::get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => (int) get_option('posts_per_page'),
        'paged' => max(1, (int) get_query_var('paged')),
        'tax_query' => [
            [
                'taxonomy' => 'topic',
                'field' => 'term_id',
                'terms' => [$term->term_id],
            ]
        ]
    ]);
}
Timber::render('templates/single-blog_topic.twig', $context);
