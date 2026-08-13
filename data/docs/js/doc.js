/**
 * 懒人导航 - 主题开发文档交互脚本
 * 功能：侧边栏导航高亮、锚点滚动、移动端菜单、搜索过滤、代码复制
 */

document.addEventListener('DOMContentLoaded', function() {
  // ===== 侧边栏导航高亮（仅顶层章节） =====
  const topSections = document.querySelectorAll('.doc-section[id]:not([id*="-"])');
  const navLinks = document.querySelectorAll('.nav-link');
  
  function updateActiveNav() {
    let current = '';
    const scrollPos = window.scrollY + 120;
    
    topSections.forEach(section => {
      const top = section.offsetTop;
      const height = section.offsetHeight;
      if (scrollPos >= top && scrollPos < top + height) {
        current = section.id;
      }
    });
    
    navLinks.forEach(link => {
      link.classList.remove('active');
      if (link.getAttribute('href') === '#' + current) {
        link.classList.add('active');
      }
    });
  }
  
  window.addEventListener('scroll', updateActiveNav, { passive: true });
  updateActiveNav();
  
  // ===== 页面加载时处理 URL hash =====
  if (window.location.hash) {
    const target = document.querySelector(window.location.hash);
    if (target) {
      setTimeout(function() {
        target.scrollIntoView({ behavior: 'auto', block: 'start' });
        updateActiveNav();
      }, 100);
    }
  }
  
  // ===== 移动端菜单 =====
  const mobileToggle = document.querySelector('.mobile-toggle');
  const sidebar = document.querySelector('.doc-sidebar');
  const overlay = document.querySelector('.doc-overlay');
  
  if (mobileToggle) {
    mobileToggle.addEventListener('click', function() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
    });
    
    overlay.addEventListener('click', function() {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    });
    
    // 点击导航后关闭菜单
    navLinks.forEach(link => {
      link.addEventListener('click', function() {
        if (window.innerWidth <= 1024) {
          sidebar.classList.remove('open');
          overlay.classList.remove('show');
        }
      });
    });
  }
  
  // ===== 返回顶部 =====
  const backToTop = document.querySelector('.back-to-top');
  
  if (backToTop) {
    window.addEventListener('scroll', function() {
      if (window.scrollY > 500) {
        backToTop.classList.add('visible');
      } else {
        backToTop.classList.remove('visible');
      }
    }, { passive: true });
    
    backToTop.addEventListener('click', function() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
  
  // ===== 代码复制功能 =====
  document.querySelectorAll('pre').forEach(function(pre) {
    const button = document.createElement('button');
    button.className = 'copy-btn';
    button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
    button.title = '复制代码';
    button.style.cssText = 'position:absolute;top:8px;right:8px;padding:6px 8px;background:rgba(255,255,255,0.1);border:none;border-radius:4px;color:#94a3b8;cursor:pointer;opacity:0;transition:opacity 0.2s;';
    
    pre.style.position = 'relative';
    pre.appendChild(button);
    
    pre.addEventListener('mouseenter', function() {
      button.style.opacity = '1';
    });
    pre.addEventListener('mouseleave', function() {
      button.style.opacity = '0';
    });
    
    button.addEventListener('click', function() {
      const code = pre.querySelector('code');
      const text = code ? code.innerText : pre.innerText;
      navigator.clipboard.writeText(text).then(function() {
        button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#48bb78" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        button.style.color = '#48bb78';
        setTimeout(function() {
          button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
          button.style.color = '#94a3b8';
        }, 2000);
      });
    });
  });
  
  // ===== 搜索过滤 =====
  const searchInput = document.getElementById('navSearch');
  
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const query = this.value.toLowerCase().trim();
      
      navLinks.forEach(link => {
        const text = link.textContent.toLowerCase();
        if (query === '' || text.includes(query)) {
          link.style.display = 'block';
        } else {
          link.style.display = 'none';
        }
      });
    });
  }
  
  // ===== 平滑滚动到锚点 =====
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Update URL hash for sharing
        history.replaceState(null, null, this.getAttribute('href'));
      }
    });
  });
  
  // ===== 章节分享按钮 =====
  document.querySelectorAll('.share-anchor').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const anchor = this.dataset.anchor;
      const url = window.location.origin + window.location.pathname + '#' + anchor;
      navigator.clipboard.writeText(url).then(function() {
        btn.classList.add('copied');
        btn.textContent = '✓';
        setTimeout(function() {
          btn.classList.remove('copied');
          btn.textContent = '🔗';
        }, 2000);
      });
    });
  });
  
  // ===== 表格响应式包装 =====
  document.querySelectorAll('.doc-content table').forEach(table => {
    if (!table.parentElement.classList.contains('table-wrapper')) {
      const wrapper = document.createElement('div');
      wrapper.className = 'table-wrapper';
      wrapper.style.cssText = 'overflow-x:auto;margin:20px 0;border-radius:8px;';
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    }
  });
  
  // ===== 打印优化 =====
  window.addEventListener('beforeprint', function() {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
  });
});
