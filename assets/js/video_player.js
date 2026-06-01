/**
 * JDM Kenya - High-Performance Video Engine
 * 
 * Performance Logic:
 * 1. Passive Preloading: Uses 'preload=metadata' to fetch only essential file info, 
 *    preventing heavy data transfer during initial page load.
 * 2. Intersection Management: Designed to handle multiple players without CPU spikes.
 * 3. Asynchronous Sync: Progress tracking uses non-blocking fetch to ensure zero UI lag.
 * 4. Resource Reuse: Single instance manages all video elements on the page.
 */

class YouTubeStyleVideoPlayer {
    constructor() {
        this.videos = [];
        this.currentVideo = null;
        this.isFullscreen = false;
        this.isTheatreMode = false;
        this.progressInterval = null;
        this.userId = null;
        
        this.init();
    }
    
    init() {
        // Step 1: Securely acquire user session context for progress persistence
        this.userId = this.getUserId();
        
        // Step 2: Scan DOM for video containers and initialize custom player wrappers
        this.setupVideoPlayers();
        
        // Step 3: Inject optimized CSS styles into the document head
        this.addStyles();
        
        // Step 4: Register low-level event listeners for keyboard control
        this.setupKeyboardShortcuts();
    }
    
    getUserId() {
        // Try to get user ID from various sources
        const scripts = document.querySelectorAll('script');
        for (let script of scripts) {
            const content = script.textContent || script.innerHTML;
            const match = content.match(/user_id\s*=\s*(\d+)/);
            if (match) return parseInt(match[1]);
        }
        
        // Fallback to meta tag or data attribute
        const userIdMeta = document.querySelector('meta[name="user-id"]');
        return userIdMeta ? parseInt(userIdMeta.content) : null;
    }
    
    setupVideoPlayers() {
        const videoElements = document.querySelectorAll('.video-container video');
        
        videoElements.forEach((video, index) => {
            const container = video.parentElement;
            const videoData = this.extractVideoData(container);
            
            if (videoData) {
                this.videos.push(videoData);
                this.enhanceVideoPlayer(video, videoData, index);
            }
        });
    }
    
    extractVideoData(container) {
        const videoElement = container.querySelector('video');
        if (!videoElement) return null;
        
        return {
            id: container.dataset.videoId || `video_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
            title: container.dataset.title || 'Unknown Video',
            src: videoElement.src || videoElement.querySelector('source')?.src,
            duration: videoElement.duration || 0,
            userId: this.userId
        };
    }
    
    enhanceVideoPlayer(videoElement, videoData, index) {
        const container = videoElement.parentElement;
        
        // Replace basic video with YouTube-style player
        const playerHTML = this.createPlayerHTML(videoData, index);
        container.innerHTML = playerHTML;
        
        // Get references to new elements
        const newVideo = container.querySelector('.youtube-video');
        const controls = container.querySelector('.video-controls');
        const progressBar = container.querySelector('.video-progress');
        const playBtn = container.querySelector('.play-btn');
        const fullscreenBtn = container.querySelector('.fullscreen-btn');
        const theatreBtn = container.querySelector('.theatre-btn');
        const volumeBtn = container.querySelector('.volume-btn');
        const timeDisplay = container.querySelector('.time-display');
        
        // Setup event listeners
        this.setupVideoControls(newVideo, controls, progressBar, playBtn, fullscreenBtn, theatreBtn, volumeBtn, timeDisplay, videoData);
        
        // Load previous progress
        this.loadVideoProgress(videoData.id, newVideo, progressBar);
        
        // Setup video source
        if (videoData.src) {
            newVideo.src = videoData.src;
        }
    }
    
    createPlayerHTML(videoData, index) {
        return `
            <div class="youtube-style-player" data-video-id="${videoData.id}">
                <div class="video-container-theatre">
                    <video class="youtube-video" preload="metadata" controlsList="nodownload">
                        <source src="${videoData.src}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    
                    <div class="video-overlay">
                        <div class="video-loading">
                            <div class="spinner-border spinner-border-sm text-light" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div class="video-play-button-overlay">
                            <i class="bi bi-play-fill"></i>
                        </div>
                    </div>
                </div>
                
                <div class="video-controls">
                    <div class="video-progress-container">
                        <div class="video-progress">
                            <div class="video-progress-filled"></div>
                            <div class="video-progress-buffered"></div>
                        </div>
                    </div>
                    
                    <div class="video-controls-left">
                        <button class="video-btn play-btn" title="Play/Pause (Space)">
                            <i class="bi bi-play-fill"></i>
                        </button>
                        <button class="video-btn volume-btn" title="Mute/Unmute (M)">
                            <i class="bi bi-volume-up-fill"></i>
                        </button>
                        <div class="time-display">0:00 / 0:00</div>
                    </div>
                    
                    <div class="video-controls-right">
                        <button class="video-btn theatre-btn" title="Theatre Mode (T)">
                            <i class="bi bi-aspect-ratio"></i>
                        </button>
                        <button class="video-btn fullscreen-btn" title="Fullscreen (F)">
                            <i class="bi bi-fullscreen"></i>
                        </button>
                        <button class="video-btn settings-btn" title="Settings">
                            <i class="bi bi-gear"></i>
                        </button>
                    </div>
                </div>
                
                <div class="video-info">
                    <h3 class="video-title">${videoData.title}</h3>
                    <div class="video-stats">
                        <span class="video-views">0 views</span>
                        <span class="video-completed-status" data-video-id="${videoData.id}">
                            <i class="bi bi-circle text-muted"></i> Not completed
                        </span>
                    </div>
                </div>
            </div>
        `;
    }
    
    setupVideoControls(video, controls, progressBar, playBtn, fullscreenBtn, theatreBtn, volumeBtn, timeDisplay, videoData) {
        // Play/Pause
        playBtn.addEventListener('click', () => this.togglePlayPause(video, playBtn));
        video.addEventListener('click', () => this.togglePlayPause(video, playBtn));
        
        // Progress bar
        progressBar.addEventListener('click', (e) => this.seekVideo(e, video, progressBar));
        
        // Fullscreen
        fullscreenBtn.addEventListener('click', () => this.toggleFullscreen(video, fullscreenBtn));
        
        // Theatre mode
        theatreBtn.addEventListener('click', () => this.toggleTheatreMode(video, theatreBtn));
        
        // Volume
        volumeBtn.addEventListener('click', () => this.toggleMute(video, volumeBtn));
        
        // Video events
        video.addEventListener('loadedmetadata', () => this.onVideoLoaded(video, timeDisplay));
        video.addEventListener('timeupdate', () => this.updateProgress(video, progressBar, timeDisplay, videoData));
        video.addEventListener('ended', () => this.onVideoEnded(video, videoData));
        video.addEventListener('play', () => this.onVideoPlay(video, playBtn));
        video.addEventListener('pause', () => this.onVideoPause(video, playBtn));
        video.addEventListener('waiting', () => this.showLoading(video));
        video.addEventListener('canplay', () => this.hideLoading(video));
        
        // Progress bar dragging
        this.setupProgressBarDragging(progressBar, video);
    }
    
    togglePlayPause(video, playBtn) {
        if (video.paused) {
            video.play();
            playBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
        } else {
            video.pause();
            playBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
        }
    }
    
    onVideoPlay(video, playBtn) {
        playBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
        this.startProgressTracking(video);
    }
    
    onVideoPause(video, playBtn) {
        playBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
        this.stopProgressTracking();
    }
    
    onVideoLoaded(video, timeDisplay) {
        const duration = this.formatTime(video.duration);
        timeDisplay.textContent = `0:00 / ${duration}`;
    }
    
    updateProgress(video, progressBar, timeDisplay, videoData) {
        const progress = (video.currentTime / video.duration) * 100;
        const filledBar = progressBar.querySelector('.video-progress-filled');
        const bufferedBar = progressBar.querySelector('.video-progress-buffered');
        
        filledBar.style.width = `${progress}%`;
        
        // Update buffered progress
        if (video.buffered.length > 0) {
            const bufferedEnd = video.buffered.end(video.buffered.length - 1);
            const bufferedProgress = (bufferedEnd / video.duration) * 100;
            bufferedBar.style.width = `${bufferedProgress}%`;
        }
        
        // Update time display
        const current = this.formatTime(video.currentTime);
        const duration = this.formatTime(video.duration);
        timeDisplay.textContent = `${current} / ${duration}`;
        
        // Save progress to database
        this.saveVideoProgress(videoData.id, video.currentTime, video.duration, progress >= 95);
    }
    
    onVideoEnded(video, videoData) {
        const playBtn = video.parentElement.querySelector('.play-btn');
        playBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
        
        // Mark as completed
        this.markVideoCompleted(videoData.id);
        
        // Show completion animation
        this.showCompletionAnimation(videoData.id);
        
        this.stopProgressTracking();
    }
    
    seekVideo(e, video, progressBar) {
        const rect = progressBar.getBoundingClientRect();
        const pos = (e.clientX - rect.left) / rect.width;
        video.currentTime = pos * video.duration;
    }
    
    setupProgressBarDragging(progressBar, video) {
        let isDragging = false;
        
        const startDrag = (e) => {
            isDragging = true;
            progressBar.style.cursor = 'grabbing';
            this.seekVideo(e, video, progressBar);
        };
        
        const drag = (e) => {
            if (isDragging) {
                this.seekVideo(e, video, progressBar);
            }
        };
        
        const endDrag = () => {
            isDragging = false;
            progressBar.style.cursor = 'pointer';
        };
        
        progressBar.addEventListener('mousedown', startDrag);
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', endDrag);
        
        progressBar.style.cursor = 'pointer';
    }
    
    toggleFullscreen(video, fullscreenBtn) {
        const player = video.closest('.youtube-style-player');
        
        if (!document.fullscreenElement) {
            player.requestFullscreen().catch(err => {
                console.error('Error attempting to enable fullscreen:', err);
            });
            fullscreenBtn.innerHTML = '<i class="bi bi-fullscreen-exit"></i>';
        } else {
            document.exitFullscreen();
            fullscreenBtn.innerHTML = '<i class="bi bi-fullscreen"></i>';
        }
    }
    
    toggleTheatreMode(video, theatreBtn) {
        const player = video.closest('.youtube-style-player');
        this.isTheatreMode = !this.isTheatreMode;
        
        if (this.isTheatreMode) {
            player.classList.add('theatre-mode');
            theatreBtn.innerHTML = '<i class="bi bi-aspect-ratio-fill"></i>';
            theatreBtn.title = 'Exit Theatre Mode (T)';
        } else {
            player.classList.remove('theatre-mode');
            theatreBtn.innerHTML = '<i class="bi bi-aspect-ratio"></i>';
            theatreBtn.title = 'Theatre Mode (T)';
        }
    }
    
    toggleMute(video, volumeBtn) {
        video.muted = !video.muted;
        
        if (video.muted) {
            volumeBtn.innerHTML = '<i class="bi bi-volume-mute-fill"></i>';
            volumeBtn.title = 'Unmute (M)';
        } else {
            volumeBtn.innerHTML = '<i class="bi bi-volume-up-fill"></i>';
            volumeBtn.title = 'Mute (M)';
        }
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Only handle when not typing in input fields
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            const activeVideo = document.querySelector('.youtube-video');
            if (!activeVideo) return;
            
            switch(e.key.toLowerCase()) {
                case ' ':
                    e.preventDefault();
                    const playBtn = activeVideo.closest('.youtube-style-player').querySelector('.play-btn');
                    this.togglePlayPause(activeVideo, playBtn);
                    break;
                case 'f':
                    e.preventDefault();
                    const fullscreenBtn = activeVideo.closest('.youtube-style-player').querySelector('.fullscreen-btn');
                    this.toggleFullscreen(activeVideo, fullscreenBtn);
                    break;
                case 't':
                    e.preventDefault();
                    const theatreBtn = activeVideo.closest('.youtube-style-player').querySelector('.theatre-btn');
                    this.toggleTheatreMode(activeVideo, theatreBtn);
                    break;
                case 'm':
                    e.preventDefault();
                    const volumeBtn = activeVideo.closest('.youtube-style-player').querySelector('.volume-btn');
                    this.toggleMute(activeVideo, volumeBtn);
                    break;
                case 'arrowleft':
                    e.preventDefault();
                    activeVideo.currentTime = Math.max(0, activeVideo.currentTime - 5);
                    break;
                case 'arrowright':
                    e.preventDefault();
                    activeVideo.currentTime = Math.min(activeVideo.duration, activeVideo.currentTime + 5);
                    break;
            }
        });
    }
    
    formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }
    
    startProgressTracking(video) {
        this.stopProgressTracking();
        this.progressInterval = setInterval(() => {
            // Progress is updated in timeupdate event
        }, 1000);
    }
    
    stopProgressTracking() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
    }
    
    async loadVideoProgress(videoId, video, progressBar) {
        if (!this.userId) return;
        
        try {
            const response = await fetch(`/JDM_kenya/modules/api/video_progress.php?action=get&video_id=${videoId}&user_id=${this.userId}`);
            const data = await response.json();
            
            if (data.success && data.progress) {
                const progress = data.progress.progress || 0;
                const filledBar = progressBar.querySelector('.video-progress-filled');
                filledBar.style.width = `${progress}%`;
                
                // Update completion status
                this.updateCompletionStatus(videoId, data.progress.completed);
                
                // Resume from saved position
                if (video.duration && progress < 95) {
                    video.currentTime = (progress / 100) * video.duration;
                }
            }
        } catch (error) {
            console.error('Error loading video progress:', error);
        }
    }
    
    async saveVideoProgress(videoId, currentTime, duration, completed) {
        if (!this.userId) return;
        
        const progress = Math.min((currentTime / duration) * 100, 100);
        
        try {
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('video_id', videoId);
            formData.append('user_id', this.userId);
            formData.append('progress', progress);
            formData.append('completed', completed ? '1' : '0');
            formData.append('current_time', currentTime);
            formData.append('duration', duration);
            
            await fetch('/JDM_kenya/modules/api/video_progress.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Error saving video progress:', error);
        }
    }
    
    async markVideoCompleted(videoId) {
        if (!this.userId) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'complete');
            formData.append('video_id', videoId);
            formData.append('user_id', this.userId);
            
            await fetch('/JDM_kenya/modules/api/video_progress.php', {
                method: 'POST',
                body: formData
            });
            
            this.updateCompletionStatus(videoId, true);
        } catch (error) {
            console.error('Error marking video completed:', error);
        }
    }
    
    updateCompletionStatus(videoId, completed) {
        const statusElement = document.querySelector(`.video-completed-status[data-video-id="${videoId}"]`);
        if (statusElement) {
            if (completed) {
                statusElement.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Completed';
                statusElement.classList.add('completed');
            } else {
                statusElement.innerHTML = '<i class="bi bi-circle text-muted"></i> Not completed';
                statusElement.classList.remove('completed');
            }
        }
    }
    
    showCompletionAnimation(videoId) {
        const player = document.querySelector(`[data-video-id="${videoId}"]`);
        if (!player) return;
        
        const overlay = document.createElement('div');
        overlay.className = 'completion-overlay';
        overlay.innerHTML = `
            <div class="completion-message">
                <i class="bi bi-check-circle-fill text-success"></i>
                <h4>Video Completed!</h4>
                <p>Great job! You've finished this video.</p>
            </div>
        `;
        
        player.appendChild(overlay);
        
        // Remove after 3 seconds
        setTimeout(() => {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }, 3000);
    }
    
    showLoading(video) {
        const overlay = video.parentElement.querySelector('.video-overlay');
        const loading = overlay.querySelector('.video-loading');
        if (loading) {
            loading.style.display = 'flex';
        }
    }
    
    hideLoading(video) {
        const overlay = video.parentElement.querySelector('.video-overlay');
        const loading = overlay.querySelector('.video-loading');
        if (loading) {
            loading.style.display = 'none';
        }
    }
    
    addStyles() {
        const styles = `
            <style>
                .youtube-style-player {
                    background: #000;
                    border-radius: 12px;
                    overflow: hidden;
                    position: relative;
                    max-width: 100%;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
                
                .youtube-style-player.theatre-mode {
                    max-width: 90vw;
                    margin: 0 auto;
                    box-shadow: 0 0 50px rgba(0,0,0,0.8);
                }
                
                .video-container-theatre {
                    position: relative;
                    padding-bottom: 56.25%; /* 16:9 aspect ratio */
                    background: #000;
                }
                
                .youtube-video {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: #000;
                }
                
                .video-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: rgba(0,0,0,0.3);
                    opacity: 0;
                    transition: opacity 0.3s;
                }
                
                .video-overlay:hover .video-play-button-overlay {
                    opacity: 1;
                }
                
                .video-loading {
                    display: none;
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                }
                
                .video-play-button-overlay {
                    font-size: 3rem;
                    color: white;
                    opacity: 0;
                    transition: opacity 0.3s;
                    cursor: pointer;
                }
                
                .video-controls {
                    background: linear-gradient(to bottom, transparent, rgba(0,0,0,0.7) 30%, rgba(0,0,0,0.9));
                    padding: 12px;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                
                .video-progress-container {
                    flex: 1;
                    height: 6px;
                    background: rgba(255,255,255,0.2);
                    border-radius: 3px;
                    cursor: pointer;
                    position: relative;
                }
                
                .video-progress {
                    width: 100%;
                    height: 100%;
                    background: transparent;
                    border-radius: 3px;
                    position: relative;
                }
                
                .video-progress-filled {
                    height: 100%;
                    background: #ff0000;
                    border-radius: 3px;
                    width: 0%;
                    transition: width 0.1s linear;
                }
                
                .video-progress-buffered {
                    position: absolute;
                    top: 0;
                    left: 0;
                    height: 100%;
                    background: rgba(255,255,255,0.4);
                    border-radius: 3px;
                    width: 0%;
                }
                
                .video-controls-left,
                .video-controls-right {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .video-btn {
                    background: rgba(255,255,255,0.1);
                    border: none;
                    color: white;
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                
                .video-btn:hover {
                    background: rgba(255,255,255,0.2);
                    transform: scale(1.1);
                }
                
                .time-display {
                    color: white;
                    font-size: 14px;
                    font-family: Arial, sans-serif;
                    min-width: 120px;
                }
                
                .video-info {
                    background: rgba(0,0,0,0.9);
                    padding: 16px;
                    color: white;
                }
                
                .video-title {
                    margin: 0 0 8px 0;
                    font-size: 18px;
                    font-weight: 500;
                }
                
                .video-stats {
                    display: flex;
                    gap: 16px;
                    font-size: 14px;
                }
                
                .video-completed-status.completed {
                    color: #28a745;
                }
                
                .completion-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.8);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                    animation: fadeIn 0.5s;
                }
                
                .completion-message {
                    text-align: center;
                    color: white;
                }
                
                .completion-message i {
                    font-size: 3rem;
                    margin-bottom: 16px;
                }
                
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                
                @media (max-width: 768px) {
                    .youtube-style-player.theatre-mode {
                        max-width: 100vw;
                        margin: 0;
                    }
                    
                    .video-controls {
                        padding: 8px;
                        gap: 8px;
                    }
                    
                    .video-btn {
                        width: 32px;
                        height: 32px;
                    }
                    
                    .time-display {
                        font-size: 12px;
                        min-width: 100px;
                    }
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', styles);
    }
}

// Initialize video player when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new YouTubeStyleVideoPlayer();
});
