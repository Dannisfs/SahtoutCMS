<?php
define('ALLOWED_ACCESS', true);

require_once __DIR__ . '/includes/paths.php';

$page_class = "home";
$header_file = $project_root . 'includes/header.php';

// Ensure header file exists before including
if (file_exists($header_file)) {
    include $header_file;
} else {
    die('Error: Header file not found.');
}

// News pagination
$news_per_page = 4;
$news_page = isset($_GET['news_page']) ? max(1, intval($_GET['news_page'])) : 1;
$news_offset = ($news_page - 1) * $news_per_page;

// Count total news
$count_query = "SELECT COUNT(*) as total FROM server_news";
$count_result = $site_db->query($count_query);
$total_news = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_news / $news_per_page);

// Query to fetch news with pagination
$query = "SELECT id, title, slug, image_url, post_date 
          FROM server_news 
          ORDER BY is_important DESC, post_date DESC 
          LIMIT $news_per_page OFFSET $news_offset";
$result = $site_db->query($query);
?>

<!-- Home Page Content -->
<div class="home-container">

    <!-- 🔁 Image Gallery Slider -->
    <section class="hero-gallery"
        style="margin-bottom: 30px; position: relative; overflow: hidden; border: 1px solid #5c3a16; box-shadow: 0 0 15px rgba(0,0,0,0.5);">
        <div class="slider" id="slider">
            <div class="slide fade"><img src="<?php echo $base_path; ?>img/homeimg/slide1.jpg" alt="Slide 1"
                    style="width:100%; display:block;"></div>
            <div class="slide fade"><img src="<?php echo $base_path; ?>img/homeimg/slide2.jpg" alt="Slide 2"
                    style="width:100%; display:block;"></div>
            <div class="slide fade"><img src="<?php echo $base_path; ?>img/homeimg/slide3.jpg" alt="Slide 3"
                    style="width:100%; display:block;"></div>
        </div>

        <!-- Slider Controls -->
        <button class="slider-nav prev" onclick="plusSlides(-1)"
            style="position: absolute; top: 50%; left: 0; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; font-size: 24px; padding: 15px; cursor: pointer; transition: 0.3s; z-index: 10;">&#10094;</button>
        <button class="slider-nav next" onclick="plusSlides(1)"
            style="position: absolute; top: 50%; right: 0; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; font-size: 24px; padding: 15px; cursor: pointer; transition: 0.3s; z-index: 10;">&#10095;</button>

        <!-- Dots -->
        <div style="text-align:center; position: absolute; bottom: 15px; width: 100%; z-index: 10;">
            <span class="dot" onclick="currentSlide(1)"
                style="cursor:pointer; height: 12px; width: 12px; margin: 0 5px; background-color: rgba(255,255,255,0.5); border-radius: 50%; display: inline-block;"></span>
            <span class="dot" onclick="currentSlide(2)"
                style="cursor:pointer; height: 12px; width: 12px; margin: 0 5px; background-color: rgba(255,255,255,0.5); border-radius: 50%; display: inline-block;"></span>
            <span class="dot" onclick="currentSlide(3)"
                style="cursor:pointer; height: 12px; width: 12px; margin: 0 5px; background-color: rgba(255,255,255,0.5); border-radius: 50%; display: inline-block;"></span>
        </div>
    </section>

    <!-- 📰 News Preview Section -->
    <section class="news-preview">
        <h2
            style="color: #ecc05b; font-family: 'Cinzel', serif; border-bottom: 1px solid #443322; padding-bottom: 10px; margin-bottom: 20px; text-transform: uppercase;">
            Latest News</h2>

        <div class="news-grid-warmane">
            <?php if ($result->num_rows === 0): ?>
                <p style="color: #888; text-align: center; padding: 20px;">
                    <?php echo translate('home_no_news', 'No news available at the time.'); ?></p>
            <?php else: ?>
                <?php while ($news = $result->fetch_assoc()): ?>
                    <div class="news-item"
                        style="display: flex; gap: 20px; margin-bottom: 20px; background: rgba(0,0,0,0.3); padding: 15px; border: 1px solid rgba(255,255,255,0.05); transition: background 0.3s;">
                        <a href="<?php echo $base_path; ?>news?slug=<?php echo htmlspecialchars($news['slug']); ?>"
                            style="display: flex; text-decoration: none; color: inherit; width: 100%;">
                            <div class="news-image"
                                style="flex-shrink: 0; width: 200px; height: 120px; overflow: hidden; border: 1px solid #333; position: relative;">
                                <img src="<?php echo $base_path . htmlspecialchars($news['image_url']); ?>"
                                    alt="<?php echo htmlspecialchars($news['title']); ?>"
                                    style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s;">
                            </div>
                            <div class="news-content" style="margin-left: 20px; flex-grow: 1;">
                                <h3 class="news-title"
                                    style="margin: 0 0 8px 0; font-size: 18px; color: #ecc05b; font-family: 'Cinzel', serif;">
                                    <?php echo htmlspecialchars($news['title']); ?></h3>
                                <p class="news-date" style="font-size: 12px; color: #888; margin: 0 0 10px 0;"><i
                                        class="far fa-clock"></i> <?php echo date('M j, Y', strtotime($news['post_date'])); ?>
                                </p>
                                <span style="font-size: 13px; color: #ccc; border-bottom: 1px dotted #666;">Read more...</span>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="news-pagination" style="display: flex; justify-content: center; gap: 8px; margin-top: 20px; padding: 15px 0;">
            <?php if ($news_page > 1): ?>
                <a href="?news_page=<?php echo $news_page - 1; ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: rgba(0,0,0,0.5); border: 1px solid #5c3a16; color: #ecc05b; text-decoration: none; border-radius: 4px; font-size: 14px; transition: all 0.3s;">&laquo;</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?news_page=<?php echo $i; ?>" 
                   style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: <?php echo $i === $news_page ? 'linear-gradient(135deg, #5c3a16, #8b6914)' : 'rgba(0,0,0,0.5)'; ?>; border: 1px solid <?php echo $i === $news_page ? '#ecc05b' : '#5c3a16'; ?>; color: <?php echo $i === $news_page ? '#fff' : '#ecc05b'; ?>; text-decoration: none; border-radius: 4px; font-family: 'Cinzel', serif; font-size: 14px; font-weight: <?php echo $i === $news_page ? '700' : '400'; ?>; transition: all 0.3s;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            <?php if ($news_page < $total_pages): ?>
                <a href="?news_page=<?php echo $news_page + 1; ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: rgba(0,0,0,0.5); border: 1px solid #5c3a16; color: #ecc05b; text-decoration: none; border-radius: 4px; font-size: 14px; transition: all 0.3s;">&raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

</div>

<?php
$footer_file = $project_root . 'includes/footer.php';
if (file_exists($footer_file)) {
    include $footer_file;
} else {
    die('Error: Footer file not found.');
}
?>

<!-- Slider Script -->
<script>
    let slideIndex = 1;
    showSlides(slideIndex);

    function plusSlides(n) {
        showSlides(slideIndex += n);
    }

    function currentSlide(n) {
        showSlides(slideIndex = n);
    }

    function showSlides(n) {
        let i;
        let slides = document.getElementsByClassName("slide");
        let dots = document.getElementsByClassName("dot");
        if (n > slides.length) { slideIndex = 1 }
        if (n < 1) { slideIndex = slides.length }
        for (i = 0; i < slides.length; i++) {
            slides[i].style.display = "none";
        }
        for (i = 0; i < dots.length; i++) {
            dots[i].className = dots[i].className.replace(" active", "");
            dots[i].style.backgroundColor = "rgba(255,255,255,0.5)";
        }
        slides[slideIndex - 1].style.display = "block";
        dots[slideIndex - 1].className += " active";
        dots[slideIndex - 1].style.backgroundColor = "#ecc05b";
    }

    // Auto Advance
    setInterval(function () { plusSlides(1); }, 6000);
</script>

<!-- Fade Animation CSS -->
<style>
    .fade {
        animation-name: fade;
        animation-duration: 1.5s;
    }

    @keyframes fade {
        from {
            opacity: .4
        }

        to {
            opacity: 1
        }
    }
</style>

<?php
if (isset($site_db)) {
    $site_db->close();
}
?>