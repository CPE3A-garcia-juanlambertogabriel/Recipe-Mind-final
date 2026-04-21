const API_BASE_URL = '/recipe-mind-final/api';

// Check API connection on load
document.addEventListener('DOMContentLoaded', () => {
    checkAPIStatus();
    loadAuthToken();
});

async function checkAPIStatus() {
    const statusIndicator = document.getElementById('apiStatus');
    const statusText = document.getElementById('statusText');
    
    try {
        const response = await fetch(`${API_BASE_URL}/search?ingredients=test`);
        if (response.ok || response.status === 400) {
            statusIndicator.className = 'status-indicator online';
            statusText.textContent = 'API is online';
        } else {
            throw new Error('API not responding');
        }
    } catch (error) {
        statusIndicator.className = 'status-indicator offline';
        statusText.textContent = 'API is offline - Make sure your backend is running';
    }
}

function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

function displayResponse(elementId, data, isError = false) {
    const element = document.getElementById(elementId);
    if (isError) {
        element.innerHTML = JSON.stringify({ error: data }, null, 2);
        element.style.borderLeft = '4px solid #dc3545';
    } else {
        element.innerHTML = JSON.stringify(data, null, 2);
        element.style.borderLeft = '4px solid #28a745';
    }
}

// Display search results as recipe cards with save buttons
function displaySearchResults(data) {
    const element = document.getElementById('searchResponse');
    if (!data || !data.length) {
        element.innerHTML = '<p>No recipes found.</p>';
        return;
    }
    
    let html = '<div class="recipes-grid">';
    data.forEach(recipe => {
        // Escape single quotes in title for onclick
        const safeTitle = recipe.title.replace(/'/g, "\\'");
        html += `
            <div class="recipe-card">
                <img src="${recipe.image}" alt="${recipe.title}">
                <h3>${recipe.title}</h3>
                <p>Used ingredients: ${recipe.usedIngredientCount}/${recipe.usedIngredientCount + recipe.missedIngredientCount}</p>
                <button onclick="saveToFavorites(${recipe.id}, '${safeTitle}', '${recipe.image}', '')">⭐ Save to Favorites</button>
            </div>
        `;
    });
    html += '</div>';
    element.innerHTML = html;
}

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tabName = btn.dataset.tab;
        
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.getElementById(`${tabName}-tab`).classList.add('active');
        
        // If favorites tab is opened, load favorites automatically
        if (tabName === 'favorites') {
            loadFavorites();
        }
    });
});

// Search Recipes
async function testSearch() {
    const ingredients = document.getElementById('ingredients').value;
    const number = document.getElementById('number').value;
    
    if (!ingredients) {
        alert('Please enter ingredients');
        return;
    }
    
    showLoading();
    
    try {
        const url = `${API_BASE_URL}/search?ingredients=${encodeURIComponent(ingredients)}&number=${number}`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (!response.ok) {
            displayResponse('searchResponse', data.error || 'Error fetching recipes', true);
        } else {
            displaySearchResults(data);
        }
    } catch (error) {
        displayResponse('searchResponse', error.message, true);
    } finally {
        hideLoading();
    }
}

// Nutrition Info
async function testNutrition() {
    const query = document.getElementById('foodQuery').value;
    
    if (!query) {
        alert('Please enter a food item');
        return;
    }
    
    showLoading();
    
    try {
        const url = `${API_BASE_URL}/nutrition?query=${encodeURIComponent(query)}`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (!response.ok) {
            displayResponse('nutritionResponse', data.error || 'Error fetching nutrition info', true);
        } else {
            displayResponse('nutritionResponse', data);
        }
    } catch (error) {
        displayResponse('nutritionResponse', error.message, true);
    } finally {
        hideLoading();
    }
}

// Generate Meal Plan
async function testMealPlan() {
    const ingredients = document.getElementById('mealIngredients').value;
    const diet = document.getElementById('diet').value;
    const skill = document.getElementById('skill').value;
    const restrictions = document.getElementById('restrictions').value;
    
    if (!ingredients) {
        alert('Please enter available ingredients');
        return;
    }
    
    showLoading();
    
    try {
        const response = await fetch(`${API_BASE_URL}/meal-plan`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...getAuthHeader()
            },
            body: JSON.stringify({
                ingredients: ingredients,
                diet: diet,
                skill: skill,
                restrictions: restrictions
            })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            displayResponse('mealPlanResponse', data.error || 'Error generating meal plan', true);
        } else {
            displayResponse('mealPlanResponse', data);
        }
    } catch (error) {
        displayResponse('mealPlanResponse', error.message, true);
    } finally {
        hideLoading();
    }
}

// Authentication Functions
async function register() {
    const username = document.getElementById('regUsername').value;
    const email = document.getElementById('regEmail').value;
    const password = document.getElementById('regPassword').value;
    
    if (!username || !email || !password) {
        alert('Please fill in all registration fields');
        return;
    }
    
    if (password.length < 6) {
        alert('Password must be at least 6 characters');
        return;
    }
    
    showLoading();
    
    try {
        const response = await fetch(`${API_BASE_URL}/auth/register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, email, password })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            displayResponse('authResponse', data.error || 'Registration failed', true);
        } else {
            displayResponse('authResponse', data);
            if (data.token) {
                saveAuthToken(data.token);
                displayToken(data.token);
            }
        }
    } catch (error) {
        displayResponse('authResponse', error.message, true);
    } finally {
        hideLoading();
    }
}

async function login() {
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    
    if (!email || !password) {
        alert('Please enter email and password');
        return;
    }
    
    showLoading();
    
    try {
        const response = await fetch(`${API_BASE_URL}/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            displayResponse('authResponse', data.error || 'Login failed', true);
        } else {
            displayResponse('authResponse', data);
            if (data.token) {
                saveAuthToken(data.token);
                displayToken(data.token);
            }
        }
    } catch (error) {
        displayResponse('authResponse', error.message, true);
    } finally {
        hideLoading();
    }
}

function saveAuthToken(token) {
    localStorage.setItem('auth_token', token);
}

function getAuthToken() {
    return localStorage.getItem('auth_token');
}

function getAuthHeader() {
    const token = getAuthToken();
    return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function displayToken(token) {
    const tokenDisplay = document.getElementById('tokenDisplay');
    const tokenElement = document.getElementById('authToken');
    tokenElement.textContent = token;
    tokenDisplay.style.display = 'block';
}

function loadAuthToken() {
    const token = getAuthToken();
    if (token) {
        displayToken(token);
    }
}

function clearToken() {
    localStorage.removeItem('auth_token');
    document.getElementById('tokenDisplay').style.display = 'none';
    alert('Auth token cleared');
}

// Helper function to make authenticated requests
async function authenticatedFetch(url, options = {}) {
    const token = getAuthToken();
    if (token) {
        options.headers = {
            ...options.headers,
            'Authorization': `Bearer ${token}`
        };
    }
    return fetch(url, options);
}

// Save recipe to favorites
async function saveToFavorites(recipeId, title, image, sourceUrl) {
    const token = getAuthToken();
    if (!token) {
        alert('Please login first to save favorites');
        return;
    }
    
    showLoading();
    try {
        const response = await fetch(`${API_BASE_URL}/user/favorites`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                recipe_id: recipeId,
                recipe_title: title,
                recipe_image: image,
                source_url: sourceUrl
            })
        });
        
        const data = await response.json();
        if (response.ok) {
            alert('Recipe saved to favorites!');
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error saving recipe: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Load favorites
async function loadFavorites() {
    const token = getAuthToken();
    if (!token) {
        alert('Please login first');
        return;
    }
    
    showLoading();
    try {
        const response = await fetch(`${API_BASE_URL}/user/favorites`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const favorites = await response.json();
        
        const container = document.getElementById('favoritesList');
        if (!favorites.length) {
            container.innerHTML = '<p>No saved recipes yet. Search and save some!</p>';
        } else {
            let html = '<div class="favorites-grid">';
            favorites.forEach(recipe => {
                html += `
                    <div class="favorite-card">
                        ${recipe.recipe_image ? `<img src="${recipe.recipe_image}" alt="${recipe.recipe_title}">` : '<div class="no-image">No image</div>'}
                        <h4>${recipe.recipe_title}</h4>
                        <button onclick="removeFavorite(${recipe.recipe_id})">Remove</button>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }
    } catch (error) {
        document.getElementById('favoritesList').innerHTML = '<p>Error loading favorites</p>';
        console.error(error);
    } finally {
        hideLoading();
    }
}

// Remove favorite
async function removeFavorite(recipeId) {
    const token = getAuthToken();
    if (!token) return;
    
    if (!confirm('Remove this recipe from favorites?')) return;
    
    showLoading();
    try {
        const response = await fetch(`${API_BASE_URL}/user/favorites?recipe_id=${recipeId}`, {
            method: 'DELETE',
            headers: { 'Authorization': `Bearer ${token}` }
        });
        if (response.ok) {
            alert('Removed from favorites');
            loadFavorites(); // Refresh list
        } else {
            const data = await response.json();
            alert('Failed to remove: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Add keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.ctrlKey && e.key === 'Enter') {
        const activeTab = document.querySelector('.tab-content.active').id;
        if (activeTab === 'search-tab') testSearch();
        else if (activeTab === 'nutrition-tab') testNutrition();
        else if (activeTab === 'mealplan-tab') testMealPlan();
    }
});