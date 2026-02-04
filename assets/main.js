import 'vite/modulepreload-polyfill'
import FlyntComponent from './scripts/FlyntComponent'

import 'lazysizes'

if (import.meta.env.DEV) {
  import('@vite/client')
}

import.meta.glob([
  '../Components/**',
  '../assets/**',
  '!**/*.js',
  '!**/*.scss',
  '!**/*.php',
  '!**/*.twig',
  '!**/screenshot.png',
  '!**/*.md'
])

window.customElements.define(
  'flynt-component',
  FlyntComponent
)

// --------------------------------------------------
// Brand JS: smooth scrolling, header shadow, fades
// --------------------------------------------------

document.addEventListener('DOMContentLoaded', () => {
  // Smooth scroll for in-page anchors (#services, #work, etc.)
  const headerEl = document.querySelector('.site-header') || document.querySelector('.mainHeader')

  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', event => {
      const targetId = anchor.getAttribute('href')

      if (!targetId || targetId === '#') return

      const targetEl = document.querySelector(targetId)
      if (!targetEl) return

      event.preventDefault()

      const headerHeight = headerEl?.offsetHeight || 0
      const targetTop = targetEl.getBoundingClientRect().top + window.pageYOffset - headerHeight - 20

      window.scrollTo({
        top: targetTop,
        behavior: 'smooth'
      })

      // Update URL without jump
      if (history.pushState) {
        history.pushState(null, '', targetId)
      }
    })
  })

  // Header scroll shadow
  if (headerEl) {
    const onScroll = () => {
      const y = window.pageYOffset || document.documentElement.scrollTop || 0
      headerEl.style.boxShadow = y > 10
        ? '0 2px 10px rgba(0, 28, 56, 0.08)'
        : 'none'
    }

    window.addEventListener('scroll', onScroll, { passive: true })
    onScroll()
  }

  // IntersectionObserver fades for key blocks
  const fadeTargets = document.querySelectorAll(
    '.service-card, .value-card, .case-study-content, .case-study-visual'
  )

  if ('IntersectionObserver' in window && fadeTargets.length > 0) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1'
          entry.target.style.transform = 'translateY(0)'
          observer.unobserve(entry.target)
        }
      })
    }, {
      root: null,
      rootMargin: '0px',
      threshold: 0.1
    })

    fadeTargets.forEach(el => {
      el.style.opacity = '0'
      el.style.transform = 'translateY(20px)'
      el.style.transition = 'opacity 0.6s ease, transform 0.6s ease'
      observer.observe(el)
    })
  }
})

