// script.js

// Example: smooth scroll for internal links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e){
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});

// Future JS: dynamic news, animations, interactive maps, etc.

// Generate Route form interaction
const genForm = document.querySelector('.gen-form');
if(genForm) {
    genForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const start = document.getElementById('start').value.trim() || 'a mysterious start';
        const dist = document.getElementById('distance').value || 5;
        const descriptions = [
            `Wind past the ancient oaks, surge up the Crimson Rise, and blaze through the Neon Glade for ${dist}km.`,
            `Your journey from <b>${start}</b> takes you along the Enchanted Ravine, across Pulse Bridge, and into Ember Fields.`,
            `Sprint through Duskwood Alley, careen by Whispering Rocks, ending at the luminescent Vista Point in style.`,
            `Begin your adventure at <b>${start}</b>, ascend to Phoenix Crest, swing through Glimmer Bend and emerge a legend!`
        ];
        document.getElementById('route-result').innerHTML = descriptions[Math.floor(Math.random()*descriptions.length)];
    });
}