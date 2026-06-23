/**
 * JDM Kenya - Global Search & Filter
 * Real-time search across users, groups, and resources
 */

class GlobalSearch {
    constructor() {
        this.searchInput = null;
        this.resultsContainer = null;
        this.isSearching = false;
        this.searchTimeout = null;
        this.minSearchLength = 2;
        this.maxResults = 10;
        
        this.init();
    }
    
    init() {
        // Create search bar if not exists
        this.createSearchBar();
        
        // Setup event listeners
        this.setupEventListeners();
    }
    
    createSearchBar() {
        // Check if search bar already exists
        if (document.getElementById('globalSearch')) {
            this.searchInput = document.getElementById('globalSearch');
            this.resultsContainer = document.getElementById('searchResults');
            return;
        }
        
        // Create search bar HTML
        const searchHTML = `
            <div class="global-search-container position-relative" style="max-width: 400px;">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="text" 
                        id="globalSearch" 
                        class="form-control border-start-0" 
                        placeholder="Search users, groups, resources..."
                        autocomplete="off"
                    >
                    <button class="btn btn-outline-secondary border-start-0" type="button" id="clearSearch">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <div id="searchResults" class="position-absolute w-100 bg-white border rounded shadow-lg mt-1" style="z-index: 1050; display: none; max-height: 400px; overflow-y: auto;"></div>
            </div>
        `;
        
        // Insert into navbar
        const navbar = document.querySelector('.navbar .container-fluid');
        if (navbar) {
            const searchDiv = document.createElement('div');
            searchDiv.className = 'ms-auto me-3';
            searchDiv.innerHTML = searchHTML;
            navbar.appendChild(searchDiv);
            
            this.searchInput = document.getElementById('globalSearch');
            this.resultsContainer = document.getElementById('searchResults');
        }
    }
    
    setupEventListeners() {
        if (!this.searchInput) return;
        
        // Search input events
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length < this.minSearchLength) {
                this.hideResults();
                return;
            }
            
            this.searchTimeout = setTimeout(() => {
                this.performSearch(query);
            }, 300);
        });
        
        // Clear search
        const clearBtn = document.getElementById('clearSearch');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.searchInput.value = '';
                this.hideResults();
                this.searchInput.focus();
            });
        }
        
        // Hide results when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.global-search-container')) {
                this.hideResults();
            }
        });
        
        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            this.handleKeyNavigation(e);
        });
    }
    
    async performSearch(query) {
        if (this.isSearching) return;
        
        this.isSearching = true;
        this.showLoading();
        
        try {
            const results = await this.fetchSearchResults(query);
            this.displayResults(results, query);
        } catch (error) {
            console.error('Search error:', error);
            this.showError('Search failed. Please try again.');
        } finally {
            this.isSearching = false;
        }
    }
    
    async fetchSearchResults(query) {
        const base = (typeof window.BASE_PATH !== 'undefined') ? window.BASE_PATH : '';
        const response = await fetch(`${base}/modules/search/search_handler.php?q=${encodeURIComponent(query)}`);
        if (!response.ok) {
            throw new Error('Search request failed');
        }
        return await response.json();
    }
    
    displayResults(results, query) {
        if (!results || results.length === 0) {
            this.showNoResults(query);
            return;
        }
        
        let html = '';
        let resultIndex = 0;
        
        // Group results by type
        const groupedResults = this.groupResultsByType(results);
        
        Object.keys(groupedResults).forEach(type => {
            if (groupedResults[type].length > 0) {
                html += this.renderResultGroup(type, groupedResults[type], query);
            }
        });
        
        this.resultsContainer.innerHTML = html;
        this.showResults();
        this.attachResultEvents();
    }
    
    groupResultsByType(results) {
        const grouped = {
            users: [],
            groups: [],
            resources: [],
            announcements: []
        };
        
        results.forEach(result => {
            if (grouped[result.type]) {
                grouped[result.type].push(result);
            }
        });
        
        return grouped;
    }
    
    renderResultGroup(type, items, query) {
        const typeIcons = {
            users: 'bi-person',
            groups: 'bi-people-fill',
            resources: 'bi-file-earmark',
            announcements: 'bi-megaphone'
        };
        
        const typeLabels = {
            users: 'Users',
            groups: 'GBS Groups',
            resources: 'Resources',
            announcements: 'Announcements'
        };
        
        let html = `
            <div class="search-category">
                <div class="px-3 py-2 bg-light border-bottom">
                    <small class="text-muted text-uppercase fw-bold">
                        <i class="bi ${typeIcons[type]} me-1"></i>
                        ${typeLabels[type]} (${items.length})
                    </small>
                </div>
                <div class="search-results-list">
        `;
        
        items.forEach((item, index) => {
            html += this.renderResultItem(item, type, query, index);
        });
        
        html += '</div></div>';
        return html;
    }
    
    renderResultItem(item, type, query, index) {
        const highlightedTitle = this.highlightText(item.title, query);
        const highlightedDescription = this.highlightText(item.description || '', query);
        
        let html = `
            <div class="search-result-item px-3 py-2 border-bottom hover-bg-light cursor-pointer" 
                 data-type="${type}" data-url="${item.url}" data-index="${index}">
                <div class="d-flex align-items-start">
        `;
        
        // Avatar/icon
        if (item.avatar) {
            html += `<img src="${item.avatar}" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;" alt="${item.title}">`;
        } else {
            const iconClass = type === 'users' ? 'bi-person-circle' : 'bi-file-earmark-text';
            html += `<i class="bi ${iconClass} text-muted me-2" style="font-size: 1.5rem;"></i>`;
        }
        
        // Content
        html += `
            <div class="flex-grow-1">
                <div class="fw-bold">${highlightedTitle}</div>
                ${highlightedDescription ? `<div class="small text-muted">${highlightedDescription}</div>` : ''}
                ${item.metadata ? `<div class="small text-info">${item.metadata}</div>` : ''}
            </div>
        `;
        
        html += '</div></div>';
        return html;
    }
    
    highlightText(text, query) {
        if (!text || !query) return text;
        
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }
    
    attachResultEvents() {
        const resultItems = this.resultsContainer.querySelectorAll('.search-result-item');
        resultItems.forEach(item => {
            item.addEventListener('click', () => {
                const url = item.dataset.url;
                if (url) {
                    window.location.href = url;
                }
            });
            
            item.addEventListener('mouseenter', () => {
                item.classList.add('bg-light');
            });
            
            item.addEventListener('mouseleave', () => {
                item.classList.remove('bg-light');
            });
        });
    }
    
    handleKeyNavigation(e) {
        const items = this.resultsContainer.querySelectorAll('.search-result-item');
        if (items.length === 0) return;
        
        let currentIndex = -1;
        const activeItem = this.resultsContainer.querySelector('.search-result-item.active');
        if (activeItem) {
            currentIndex = parseInt(activeItem.dataset.index);
            activeItem.classList.remove('active');
        }
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                currentIndex = Math.min(currentIndex + 1, items.length - 1);
                break;
            case 'ArrowUp':
                e.preventDefault();
                currentIndex = Math.max(currentIndex - 1, 0);
                break;
            case 'Enter':
                e.preventDefault();
                if (currentIndex >= 0 && items[currentIndex]) {
                    items[currentIndex].click();
                }
                return;
            case 'Escape':
                this.hideResults();
                return;
        }
        
        if (currentIndex >= 0 && items[currentIndex]) {
            items[currentIndex].classList.add('active', 'bg-primary', 'text-white');
            items[currentIndex].scrollIntoView({ block: 'nearest' });
        }
    }
    
    showLoading() {
        this.resultsContainer.innerHTML = `
            <div class="px-3 py-4 text-center">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Searching...</span>
                </div>
                <div class="small text-muted mt-2">Searching...</div>
            </div>
        `;
        this.showResults();
    }
    
    showNoResults(query) {
        this.resultsContainer.innerHTML = `
            <div class="px-3 py-4 text-center">
                <i class="bi bi-search text-muted" style="font-size: 2rem;"></i>
                <div class="text-muted mt-2">No results found for "${query}"</div>
                <div class="small text-muted">Try different keywords or check spelling</div>
            </div>
        `;
        this.showResults();
    }
    
    showError(message) {
        this.resultsContainer.innerHTML = `
            <div class="px-3 py-4 text-center">
                <i class="bi bi-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                <div class="text-danger mt-2">${message}</div>
            </div>
        `;
        this.showResults();
    }
    
    showResults() {
        this.resultsContainer.style.display = 'block';
    }
    
    hideResults() {
        this.resultsContainer.style.display = 'none';
    }
}

// Initialize global search when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new GlobalSearch();
});

// Add custom styles
const searchStyles = `
    <style>
        .search-result-item.active {
            background-color: #0d6efd !important;
            color: white !important;
        }
        .search-result-item.active mark {
            background-color: #ffeb3b;
            color: #000;
        }
        .search-category {
            margin-bottom: 0.5rem;
        }
        .search-category:last-child {
            margin-bottom: 0;
        }
        .hover-bg-light:hover {
            background-color: #f8f9fa !important;
        }
        mark {
            background-color: #fff3cd;
            padding: 0;
            border-radius: 2px;
        }
        .global-search-container {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
`;

// Inject styles
document.head.insertAdjacentHTML('beforeend', searchStyles);
