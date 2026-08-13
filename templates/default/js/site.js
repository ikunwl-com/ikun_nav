(function() {
    // CSRF Token 获取
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // 访问站点按钮 - 点击即计数+1，然后跳转到 go.php
    var btnVisit = document.querySelector('.btn-visit');
    if (btnVisit) {
        btnVisit.addEventListener('click', function(e) {
            var siteId = this.dataset.siteId;
            var url = this.getAttribute('href');
            if (!siteId || !url) return;
            
            e.preventDefault();
            
            // 发送点击计数（不等待响应，直接跳转）
            fetch('/api/?endpoint=click', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + encodeURIComponent(siteId)
            }).catch(function() {});
            
            // 跳转到 go.php 过渡页
            window.open(url, '_blank');
        });
    }

    // 更新数据按钮
    var btnUpdateMeta = document.getElementById('btnUpdateMeta');
    if (btnUpdateMeta) {
        btnUpdateMeta.addEventListener('click', function() {
            var siteId = this.dataset.siteId;
            var url = this.dataset.url;
            var originalText = this.innerHTML;
            
            btnUpdateMeta.disabled = true;
            btnUpdateMeta.innerHTML = '<svg class="spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg> 更新中...';
            
            fetch('/api/?endpoint=update-meta', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify({site_id: siteId, url: url})
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('数据更新成功！');
                    location.reload();
                } else {
                    alert(data.message || '更新失败，请重试');
                    btnUpdateMeta.disabled = false;
                    btnUpdateMeta.innerHTML = originalText;
                }
            })
            .catch(function() {
                alert('网络错误，请重试');
                btnUpdateMeta.disabled = false;
                btnUpdateMeta.innerHTML = originalText;
            });
        });
    }

    var ratingStars = document.getElementById('ratingStars');
    if (!ratingStars) return;
    var stars = ratingStars.querySelectorAll('.star');
    var siteId = ratingStars.dataset.siteId;

    stars.forEach(function(star) {
        star.addEventListener('click', function() {
            var rating = parseInt(this.dataset.rating);
            var formData = new FormData();
            formData.append('id', siteId);
            formData.append('rating', rating);

            fetch('/api/rate', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.code === 0) {
                    updateRatingDisplay(data.data.avg_rating, data.data.total_ratings);
                } else {
                    alert(data.msg || '评分失败');
                }
            })
            .catch(function() {
                alert('网络错误，请重试');
            });
        });

        star.addEventListener('mouseenter', function() {
            var rating = parseInt(this.dataset.rating);
            highlightStars(rating);
        });

        star.addEventListener('mouseleave', function() {
            var currentRating = parseFloat(document.getElementById('ratingAvg').textContent) || 0;
            highlightStars(Math.round(currentRating));
        });
    });

    function highlightStars(count) {
        stars.forEach(function(star, idx) {
            if (idx < count) {
                star.classList.add('active');
            } else {
                star.classList.remove('active');
            }
        });
    }

    // 趋势图表
    (function() {
        var ctx = document.getElementById('trendChart');
        if (!ctx) return;

        // 读取服务器返回的真实日统计数据
        var trendData = [];
        try {
            trendData = JSON.parse(ctx.dataset.trend || '[]');
        } catch(e) {
            trendData = [];
        }

        // 如果没有数据，显示空状态
        if (!trendData || trendData.length === 0) {
            var parent = ctx.parentNode;
            parent.innerHTML = '<div style="text-align:center;color:#888;padding:20px 0;">暂无趋势数据</div>';
            return;
        }

        // 提取标签和数据
        var labels = [];
        var viewsData = [];
        var clicksData = [];

        trendData.forEach(function(item) {
            labels.push(item.date);
            viewsData.push(item.views);
            clicksData.push(item.clicks);
        });

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '访问量',
                        data: viewsData,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: '#667eea'
                    },
                    {
                        label: '点击量',
                        data: clicksData,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: '#f59e0b'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 16,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 10,
                        cornerRadius: 6,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                var value = context.parsed.y || 0;
                                return label + ': ' + value;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11 },
                            color: '#999'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.04)',
                            drawBorder: false
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#999',
                            stepSize: 1,
                            callback: function(value) {
                                return value === 0 ? '0' : value;
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    })();

    function updateRatingDisplay(avg, total) {
        document.getElementById('ratingAvg').textContent = avg.toFixed(1);
        document.getElementById('ratingCount').textContent = '分 · ' + total + ' 人评分';
        highlightStars(Math.round(avg));
    }

    // ===== 问题反馈弹窗 =====
    var btnFeedback = document.getElementById('btnFeedback');
    var feedbackModal = document.getElementById('feedbackModal');
    if (btnFeedback && feedbackModal) {
        btnFeedback.addEventListener('click', function(e) {
            e.preventDefault();
            openFeedback();
        });
    }

    // 反馈类型切换
    var fbTypeItems = document.querySelectorAll('.fb-type-item');
    fbTypeItems.forEach(function(item) {
        item.addEventListener('click', function() {
            fbTypeItems.forEach(function(i) { i.classList.remove('active'); });
            this.classList.add('active');
            var radio = this.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        });
    });
})();

function openFeedback() {
    var modal = document.getElementById('feedbackModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeFeedback() {
    var modal = document.getElementById('feedbackModal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    // 重置表单
    var content = document.getElementById('fbContent');
    var email = document.getElementById('fbEmail');
    var msg = document.getElementById('fbMsg');
    if (content) content.value = '';
    if (email) email.value = '';
    if (msg) msg.innerHTML = '';
    // 重置类型
    var items = document.querySelectorAll('.fb-type-item');
    items.forEach(function(item) { item.classList.remove('active'); });
    if (items[0]) items[0].classList.add('active');
    var radios = document.querySelectorAll('input[name="fbType"]');
    if (radios[0]) radios[0].checked = true;
}

function submitFeedback() {
    var btnFeedback = document.getElementById('btnFeedback');
    var siteId = btnFeedback ? btnFeedback.dataset.siteId : '0';
    var content = document.getElementById('fbContent');
    var email = document.getElementById('fbEmail');
    var btn = document.getElementById('fbSubmitBtn');
    var msg = document.getElementById('fbMsg');
    var typeRadio = document.querySelector('input[name="fbType"]:checked');

    if (!content || !content.value.trim()) {
        if (msg) msg.innerHTML = '<span style="color:#e53e3e;"><i class="ti ti-alert-circle"></i> 请填写反馈内容</span>';
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader-2 spin"></i> 提交中...';
    }

    fetch('/api/?endpoint=feedback', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            site_id: siteId,
            type: typeRadio ? typeRadio.value : 'other',
            content: content.value.trim(),
            email: email ? email.value.trim() : ''
        })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            if (msg) msg.innerHTML = '<span style="color:#16a34a;"><i class="ti ti-check"></i> ' + data.message + '</span>';
            setTimeout(function() { closeFeedback(); }, 1500);
        } else {
            if (msg) msg.innerHTML = '<span style="color:#e53e3e;"><i class="ti ti-alert-circle"></i> ' + (data.message || '提交失败') + '</span>';
        }
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-send"></i> 提交反馈';
        }
    })
    .catch(function() {
        if (msg) msg.innerHTML = '<span style="color:#e53e3e;"><i class="ti ti-alert-circle"></i> 网络错误，请重试</span>';
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-send"></i> 提交反馈';
        }
    });
}
