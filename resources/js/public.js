/* Public site scripts. */
import './accessibility';
import { initHeroSliders } from './hero-slider';
import { initAnnouncements } from './announcements';
import { initSocialPosts } from './social-carousel';

document.addEventListener('DOMContentLoaded', () => {
    initHeroSliders();
    initAnnouncements();
    initSocialPosts();
});
