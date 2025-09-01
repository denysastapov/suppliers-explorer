(function () {
  "use strict";

  /* ---------- Helpers ---------- */

  function $(a, b) {
    if (!a && !b) return null;
    if (typeof a === "string" && b && typeof b.querySelector === "function")
      return b.querySelector(a);
    if (a && typeof a.querySelector === "function") return a.querySelector(b);
    return null;
  }
  function $all(a, b) {
    var root, sel;
    if (
      typeof a === "string" &&
      b &&
      typeof b.querySelectorAll === "function"
    ) {
      root = b;
      sel = a;
    } else {
      root = a;
      sel = b;
    }
    if (!root || !sel || typeof root.querySelectorAll !== "function") return [];
    return Array.prototype.slice.call(root.querySelectorAll(sel));
  }
  function createEl(tag, cls, html) {
    var el = document.createElement(tag);
    if (cls) el.className = cls;
    if (html != null) el.innerHTML = html;
    return el;
  }
  function debounce(fn, ms) {
    var t;
    return function () {
      clearTimeout(t);
      var a = arguments,
        th = this;
      t = setTimeout(function () {
        fn.apply(th, a);
      }, ms || 250);
    };
  }
  function loadTopLevelsIfNeeded(panel, then) {
    if (seData.topLevels && seData.topLevels.length) {
      renderTopList(panel, seData.topLevels);
      then && then();
      return;
    }
    if (!seData.remote) {
      renderTopList(panel, []);
      then && then();
      return;
    }
    renderTopList(panel, []);
    fetch(
      seData.termsBase + "/top-level-category?per_page=100&_fields=slug,name",
      { credentials: "omit" }
    )
      .then((r) => r.json())
      .then((arr) => {
        seData.topLevels = Array.isArray(arr) ? arr : [];
        renderTopList(panel, seData.topLevels);
        then && then();
      })
      .catch(() => {
        renderTopList(panel, []);
        then && then();
      });
  }

  function renderTopList(panel, items) {
    var list = $(panel, ".se-top-list");
    if (!list) return;
    list.innerHTML = "";
    (items || []).forEach(function (it) {
      var row = createEl("label", "se-top-item");
      row.dataset.slug = it.slug;
      row.innerHTML =
        '<input type="checkbox" value="' +
        it.slug +
        '"> <span>' +
        it.name +
        "</span>";
      list.appendChild(row);
    });
  }
  function collectSelected(container) {
    return $all(container, ".se-top-item input:checked").map(function (i) {
      return i.value;
    });
  }
  function updateSelectedCount(container) {
    var countEl = $(container, ".se-selected-count");
    if (!countEl) return;
    countEl.textContent = $all(container, ".se-top-item input:checked").length;
  }
  function gridCols(grid) {
    var gtc = getComputedStyle(grid).gridTemplateColumns;
    var n = gtc ? gtc.split(" ").length : 5;
    return isNaN(n) || !n ? 5 : n;
  }

  /* ---------- Detail row inside grid ---------- */

  function closeDetail(container) {
    var grid = $(container, ".se-grid");
    if (!grid) return;
    var row = $(grid, ".se-detail-row");
    if (row && row.parentNode) row.parentNode.removeChild(row);
  }

  function ensureDetailRowAfterCard(container, cardEl) {
    var grid = $(container, ".se-grid");
    if (!grid || !cardEl) return null;

    var colCount = gridCols(grid);
    var cards = $all(grid, ".se-card");
    var idx = cards.indexOf(cardEl);
    if (idx === -1) return null;
    var rowEndIdx = Math.min(
      (Math.floor(idx / colCount) + 1) * colCount - 1,
      cards.length - 1
    );
    var insertAfter = cards[rowEndIdx];

    var detailRow = $(grid, ".se-detail-row");
    var isNew = false;
    if (!detailRow) {
      isNew = true;
      detailRow = createEl("div", "se-detail-row");
      detailRow.innerHTML =
        '<button type="button" class="se-detail-close" aria-label="Close">×</button><div class="se-detail-inner">Loading…</div>';
    }

    var currentH = detailRow.offsetHeight;
    if (currentH) {
      detailRow.style.overflow = "hidden";
      detailRow.style.maxHeight = currentH + "px";
    }

    if (insertAfter.nextSibling)
      grid.insertBefore(detailRow, insertAfter.nextSibling);
    else grid.appendChild(detailRow);

    var closeBtn = $(detailRow, ".se-detail-close");
    if (closeBtn)
      closeBtn.onclick = function () {
        closeDetail(container);
      };

    if (isNew) animateDetailOpen(detailRow);
    else animateDetailReflow(detailRow);

    return detailRow;
  }

  function animateDetailOpen(el) {
    var inner = $(el, ".se-detail-inner");
    if (!inner) return;
    el.style.overflow = "hidden";
    el.style.maxHeight = "0px";
    el.style.opacity = "0";
    requestAnimationFrame(function () {
      el.style.transition = "max-height 260ms ease, opacity 200ms ease";
      el.style.maxHeight = inner.scrollHeight + 32 + "px";
      el.style.opacity = "1";
      setTimeout(function () {
        el.style.maxHeight = "";
        el.style.transition = "";
        el.style.overflow = "";
      }, 320);
    });
  }
  function animateDetailReflow(el) {
    var inner = $(el, ".se-detail-inner");
    if (!inner) return;
    var h = inner.scrollHeight + 32;
    el.style.overflow = "hidden";
    el.style.transition = "max-height 220ms ease";
    el.style.maxHeight = h + "px";
    setTimeout(function () {
      el.style.maxHeight = "";
      el.style.transition = "";
      el.style.overflow = "";
    }, 240);
  }

  function smoothSwapDetail(detailRow, html) {
    var inner = $(detailRow, ".se-detail-inner");
    if (!inner) return;
    var startH = inner.offsetHeight;
    var tmp = document.createElement("div");
    tmp.className = "se-detail-inner se-detail-inner--tmp";
    tmp.innerHTML = html;

    inner.parentNode.appendChild(tmp);
    var endH = tmp.scrollHeight;
    inner.parentNode.removeChild(tmp);

    detailRow.style.overflow = "hidden";
    detailRow.style.transition = "max-height 240ms ease";
    detailRow.style.maxHeight = Math.max(startH, endH) + 32 + "px";

    inner.style.transition = "opacity 150ms ease";
    inner.style.opacity = "0";
    setTimeout(function () {
      inner.innerHTML = html;
      inner.style.opacity = "1";
      detailRow.style.maxHeight = inner.scrollHeight + 32 + "px";
      setTimeout(function () {
        detailRow.style.maxHeight = "";
        detailRow.style.transition = "";
        detailRow.style.overflow = "";
      }, 260);
    }, 160);
  }

  /* ---------- Fetch & render ---------- */

  function applyFilter(container, opts) {
    opts = opts || {};
    var grid = $(container, ".se-grid");
    if (!grid) return;

    if (opts.reset) closeDetail(container);

    if (!container._seReq) container._seReq = 0;
    var reqId = ++container._seReq;

    var page = opts.reset ? 1 : parseInt(grid.dataset.page || "1", 10);
    var qInp = $(container, ".se-q");
    var q = qInp ? qInp.value.trim() : "";
    var selected = collectSelected(container);

    var url = new URL(seData.restBase + "/list");
    url.searchParams.set("page", page);
    url.searchParams.set("per_page", seData.perPage || 15);
    url.searchParams.set("orderby", "name");
    url.searchParams.set("order", "asc");
    if (q) url.searchParams.set("q", q);
    selected.forEach(function (slug) {
      url.searchParams.append("top[]", slug);
    });

    var preH = grid.offsetHeight;
    grid.style.minHeight = preH ? preH + "px" : "";
    grid.classList.add("se-fade");
    grid.classList.add("se-busy");

    fetch(url.toString(), { credentials: "same-origin" })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (reqId !== container._seReq) return;

        grid.dataset.page = data.page || 1;
        grid.dataset.pages = data.pages || 1;

        if (opts.reset) grid.innerHTML = "";

        (data.items || []).forEach(function (it) {
          var card = createEl("div", "se-card");
          card.innerHTML =
            '<div class="se-logo">' +
            (it.logo
              ? '<img loading="lazy" src="' +
                it.logo +
                '" alt="" width="320" height="120">'
              : "") +
            "</div>" +
            '<div class="se-title">' +
            it.title +
            "</div>";
          card.addEventListener("click", function () {
            openDetail(container, it.id, card);
          });
          grid.appendChild(card);
        });

        var lm = $(container, ".se-loadmore");
        if (lm && lm.parentElement) {
          if ((data.page || 1) >= (data.pages || 1))
            lm.parentElement.style.display = "none";
          else lm.parentElement.style.display = "";
        }
      })
      .catch(function (_e) {})
      .finally(function () {
        if (reqId !== container._seReq) return;
        requestAnimationFrame(function () {
          grid.classList.remove("se-busy");
          grid.classList.remove("se-fade");
          setTimeout(function () {
            grid.style.minHeight = "";
          }, 120);
        });
      });
  }

  function openDetail(container, id, cardEl) {
    var grid = $(container, ".se-grid");
    if (!grid || !cardEl) return;

    var detailRow = ensureDetailRowAfterCard(container, cardEl);
    if (!detailRow) return;
    var inner = $(detailRow, ".se-detail-inner");
    if (inner)
      inner.innerHTML = '<div class="se-detail-loading">Loading…</div>';

    var url = new URL(seData.restBase + "/single");
    url.searchParams.set("id", id);

    fetch(url.toString(), { credentials: "same-origin" })
      .then(function (r) {
        return r.json();
      })
      .then(function (d) {
        if (!inner) return;

        var html =
          '<div class="se-detail-grid">' +
          '<div class="se-detail-col se-detail-col--media">' +
          (d.logo
            ? '<div class="se-detail-logo"><img src="' +
              d.logo +
              '" alt="" width="420" height="220"></div>'
            : "") +
          "</div>" +
          '<div class="se-detail-col se-detail-col--content">' +
          '<h3 class="se-detail-title">' +
          d.title +
          "</h3>" +
          '<div class="se-detail-content">' +
          (d.content || "") +
          "</div>" +
          '<div class="se-detail-actions"><a href="#enquire" class="se-enquire">Enquire now</a></div>' +
          "</div>" +
          "</div>";

        smoothSwapDetail(detailRow, html);
      })
      .catch(function (_e) {
        if (inner)
          inner.innerHTML =
            '<div class="se-detail-error">Error loading details</div>';
      });
  }

  /* ---------- Init ---------- */

  function init(container) {
    var dropbtn = $(container, ".se-dropbtn");
    var panel = $(container, ".se-dropdown-panel");
    var typeInp = $(panel, ".se-type-search");
    var topList = $(panel, ".se-top-list");
    var grid = $(container, ".se-grid");

    loadTopLevelsIfNeeded(panel, function () {
      var grid = $(container, ".se-grid");
      if (grid) grid.dataset.page = 1;
      applyFilter(container, { reset: true });
    });

    renderTopList(panel, seData.topLevels || []);

    if (dropbtn && panel) {
      dropbtn.addEventListener("click", function () {
        var expanded = dropbtn.getAttribute("aria-expanded") === "true";
        dropbtn.setAttribute("aria-expanded", String(!expanded));
        panel.hidden = expanded;
      });
      document.addEventListener("click", function (e) {
        if (!container.contains(e.target)) {
          panel.hidden = true;
          dropbtn.setAttribute("aria-expanded", "false");
        }
      });
    }

    if (typeInp) {
      typeInp.addEventListener("input", function () {
        var q = typeInp.value.trim().toLowerCase();
        $all(panel, ".se-top-item").forEach(function (row) {
          var name = row.textContent.trim().toLowerCase();
          row.style.display = name.indexOf(q) !== -1 ? "" : "none";
        });
      });
    }

    var applySelectedDebounced = debounce(function () {
      if (grid) {
        grid.dataset.page = 1;
      }
      applyFilter(container, { reset: true });
    }, 250);

    if (topList) {
      topList.addEventListener("change", function () {
        updateSelectedCount(container);
        applySelectedDebounced();
      });
    }

    var qInput = $(container, ".se-q");
    if (qInput) {
      var live = debounce(function () {
        if (grid) {
          grid.dataset.page = 1;
        }
        applyFilter(container, { reset: true });
      }, 300);
      qInput.addEventListener("input", live);
      qInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          live();
        }
      });
    }

    var loadMore = $(container, ".se-loadmore");
    if (loadMore) {
      loadMore.addEventListener("click", function () {
        var page = parseInt(grid.dataset.page || "1", 10);
        var pages = parseInt(grid.dataset.pages || "1", 10);
        if (page < pages) {
          grid.dataset.page = page + 1;
          applyFilter(container, { reset: false });
        }
      });
    }

    if (grid) grid.dataset.page = 1;
    applyFilter(container, { reset: true });
  }

  document.addEventListener("DOMContentLoaded", function () {
    $all(document, ".se-explorer").forEach(init);
  });
})();
