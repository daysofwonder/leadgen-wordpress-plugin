<?php

/**
 * The template for displaying confirmation page
 */

get_header('', array('theme' => 'dark'));
?>
<main>
    <article class="article">
        <div class="container">
            <div class="row">
                <div class="col col-md-offset-3 col-md-6">
                    <div class="article__top">
                        <div class="article__body">
                            <p class="ft-center card-article__title">
                                <?php
                                if (
                                    isset($_GET)
                                    && is_array($_GET)
                                    && isset($_GET['key'])
                                ) {
                                    echo $_GET['key'] == 'valid'
                                        ? __('Your subscription is confirmed.', 'unboxnow') : __('Subscription key is not valid.', 'unboxnow');
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </article>
</main>

<?php
get_footer();
