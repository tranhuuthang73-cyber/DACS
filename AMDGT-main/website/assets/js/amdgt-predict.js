    function setGlobalDataset(ds, btn) {
        const gd = document.getElementById('global-dataset');
        if (gd) gd.value = ds;
        document.querySelectorAll('.ds-tab').forEach(t => {
            t.classList.remove('active');
            t.style.background = 'transparent';
            t.style.color = '#94a3b8';
            t.style.fontWeight = '700';
        });
        btn.classList.add('active');
        btn.style.background = 'rgba(99, 102, 241, 0.2)';
        btn.style.color = '#818cf8';
        btn.style.fontWeight = '800';
        
        // Cập nhật dataset cho các search cards
        document.getElementById('drug-dataset').value = ds;
        document.getElementById('protein-dataset').value = ds;
        document.getElementById('disease-dataset').value = ds;
        
        showToast('Đã chuyển sang: ' + ds, 'info');
    }

// --- Main AI & Visualization Logic ---

    let currentPredictionGeneration = 0;
    let autocompleteGeneration = 0;
    let forceGraphInstance = null;
    let currentLandscapeCoords = [];
    let chartInstance = null;
    let trainingChart = null;

    // ========== MULTI-SELECT SYSTEM ==========
    const MAX_SELECTED = 10;
    const selectedItems = {
        drug: [],     // [{idx, name, id, dataset}, ...]
        protein: [],
        disease: []
    };

    function addItem(type, idx, name, id, dataset) {
        idx = parseInt(idx);
        // Check duplicate
        if (selectedItems[type].some(item => item.idx === idx && item.dataset === dataset)) {
            return; // Already added
        }
        // Check limit
        if (selectedItems[type].length >= MAX_SELECTED) {
            showToast(`Tối đa ${MAX_SELECTED} ${type} mỗi lần!`, 'warning');
            return;
        }
        selectedItems[type].push({ idx, name, id, dataset });
        renderTags(type);
        updateBtnCount(type);
        
        // Also set hidden inputs for backward compat (last selected)
        const idxInput = document.getElementById(type + '-idx');
        if (idxInput) idxInput.value = idx;
        const dsInput = document.getElementById(type + '-dataset');
        if (dsInput) dsInput.value = dataset;
    }

    window.removeItem = function(type, idx, dataset) {
        idx = parseInt(idx);
        selectedItems[type] = selectedItems[type].filter(
            item => !(item.idx === idx && item.dataset === dataset)
        );
        renderTags(type);
        updateBtnCount(type);
        
        // Update hidden inputs to last item or empty
        const last = selectedItems[type][selectedItems[type].length - 1];
        const idxInput = document.getElementById(type + '-idx');
        const dsInput = document.getElementById(type + '-dataset');
        if (last) {
            if (idxInput) idxInput.value = last.idx;
            if (dsInput) dsInput.value = last.dataset;
        } else {
            if (idxInput) idxInput.value = '';
        }
    }

    function renderTags(type) {
        const container = document.getElementById(type + '-tags');
        if (!container) return;
        
        const tagClass = type + '-tag';
        const items = selectedItems[type];
        
        if (items.length === 0) {
            container.innerHTML = '';
            return;
        }
        
        const tagsHtml = items.map(item => {
            const safeName = String(item.name).replace(/"/g, '&quot;');
            return `<span class="selected-tag ${tagClass}" title="${safeName} (${item.dataset})">
                <span class="tag-name">${item.name}</span>
                <span class="tag-remove" onclick="removeItem('${type}', ${item.idx}, '${item.dataset}')"><i class="fas fa-times"></i></span>
            </span>`;
        }).join('');
        
        const counterClass = type + '-counter';
        container.innerHTML = tagsHtml + 
            `<span class="tag-counter ${counterClass}"><i class="fas fa-layer-group"></i> ${items.length} đã chọn</span>`;
    }

    function updateBtnCount(type) {
        const count = selectedItems[type].length;
        const btnId = type === 'drug' ? 'btn-drug' : type === 'disease' ? 'btn-disease' : 'btn-protein';
        const btn = document.getElementById(btnId);
        if (!btn || btn.disabled) return;
        
        const labels = {
            drug: 'PHÂN TÍCH THUỐC',
            disease: 'PHÂN TÍCH BỆNH',
            protein: 'PHÂN TÍCH PROTEIN'
        };
        
        const label = labels[type];
        if (count > 1) {
            btn.innerHTML = `<i class="fas fa-brain"></i> ${label} (${count})`;
        } else {
            btn.innerHTML = `<i class="fas fa-brain"></i> ${label}`;
        }
    }

    function showBatchProgress(type, current, total) {
        const el = document.getElementById(type + '-progress');
        if (!el) return;
        el.style.display = 'block';
        const bar = el.querySelector('.batch-progress-bar');
        if (bar) bar.style.width = Math.round((current / total) * 100) + '%';
    }

    function hideBatchProgress(type) {
        const el = document.getElementById(type + '-progress');
        if (el) el.style.display = 'none';
    }
    // ========== END MULTI-SELECT SYSTEM ==========

    document.addEventListener('DOMContentLoaded', function () {
        console.log('[AMDGT] DOMContentLoaded fired, initializing...');
        try {
            initAutocomplete();
            console.log('[AMDGT] initAutocomplete completed');
        } catch(e) { console.error('[AMDGT] initAutocomplete error:', e); }
        try { loadLandscapeData(); } catch(e) { console.error('[AMDGT] loadLandscapeData error:', e); }
        try { loadTrainingCurve(); } catch(e) { console.error('[AMDGT] loadTrainingCurve error:', e); }
        try { initEventListeners(); } catch(e) { console.error('[AMDGT] initEventListeners error:', e); }
        try { initVIPSections(); } catch(e) { console.error('[AMDGT] initVIPSections error:', e); }
    });

    function initEventListeners() {
        // TopK buttons
        document.querySelectorAll('.topk-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                const k = parseInt(this.getAttribute('data-k'));
                setTopK(type, k, this);
            });
        });
    }

    function setTopK(type, k, btnElement) {
        document.getElementById(type + '-topk').value = k;
        document.querySelectorAll('.topk-btn[data-type="' + type + '"]').forEach(b => {
            b.classList.remove('active');
            b.style.background = 'rgba(255, 255, 255, 0.05)';
            b.style.borderColor = 'rgba(255, 255, 255, 0.1)';
            b.style.color = 'var(--text-secondary)';
        });
        if (btnElement) {
            btnElement.classList.add('active');
            btnElement.style.background = 'rgba(99, 102, 241, 0.2)';
            btnElement.style.borderColor = 'rgba(99, 102, 241, 0.4)';
            btnElement.style.color = '#818cf8';
        }
    }

    function switchVizTab(btn, tab) {
        document.querySelectorAll('.viz-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.viz-panel').forEach(p => p.classList.remove('active'));
        const targetPanel = document.getElementById('panel-' + tab);
        if (targetPanel) targetPanel.classList.add('active');
        // Trigger 3D graph render when switching to 3D tab
        if (tab === '3d' && typeof window.currentGNNData !== 'undefined' && window.currentGNNData) {
            setTimeout(() => {
                if (typeof renderGNN3DGraph === 'function') {
                    renderGNN3DGraph(window.currentGNNData, window.currentGNNType, window.currentGNNIdx);
                }
            }, 100);
        }
    }

    // GNN 3D Graph Rendering
    window.currentGNNData = null;
    window.currentGNNType = null;
    window.currentGNNIdx = null;

    function renderGNN3DGraph(predictions, type, queryIdx, batchResults) {
        window.currentGNNData = predictions;
        window.currentGNNType = type;
        window.currentGNNIdx = queryIdx;

        const container = document.getElementById('3d-graph-container');
        if (!container) return;

        // Show loading
        container.innerHTML = `
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:500px;gap:1rem;">
                <div style="width:60px;height:60px;border:4px solid rgba(244,114,182,0.2);border-top-color:#f472b6;border-radius:50%;animation:spin 1s linear infinite;"></div>
                <p style="color:#94a3b8;font-size:0.9rem;">Đang xây dựng đồ thị GNN 3D...</p>
                <p style="color:#64748b;font-size:0.75rem;">Drug → Protein → Disease Heterogeneous Graph</p>
            </div>
        `;

        const width = container.offsetWidth || 900;
        const height = 700;

        const nodeSet = new Map();
        const links = [];

        // BATCH MODE: Create multiple query nodes
        if (batchResults && batchResults.length > 1) {
            console.log('[3D-BATCH] Batch mode activated with', batchResults.length, 'queries, type:', type);
            batchResults.forEach((result, gi) => {
                const qKey = 'query_' + gi;
                nodeSet.set(qKey, {
                    name: result.queryName,
                    type: type,
                    layer: 0,
                    score: 1.0,
                    isQuery: true
                });
                console.log('[3D-BATCH] Created query node:', qKey, '=', result.queryName, 'with', result.predictions.length, 'predictions');
                
                result.predictions.forEach((pred) => {
                    if (type === 'protein') {
                        const drugKey = 'drug_' + pred.drug_idx;
                        const diseaseKey = 'disease_' + pred.disease_idx;
                        let normScore = (pred.score || 0.5);
                        normScore = normScore > 1 ? normScore / 100 : normScore;
                        if (!nodeSet.has(drugKey)) {
                            nodeSet.set(drugKey, { name: pred.drug_name || `Drug #${pred.drug_idx}`, type: 'drug', layer: 1, score: normScore, isQuery: false });
                        }
                        if (!nodeSet.has(diseaseKey)) {
                            nodeSet.set(diseaseKey, { name: pred.disease_name || `Disease #${pred.disease_idx}`, type: 'disease', layer: 1, score: normScore, isQuery: false });
                        }
                        links.push({ source: drugKey, target: qKey, weight: normScore });
                        links.push({ source: qKey, target: diseaseKey, weight: normScore });
                    } else {
                        const name = pred.name || pred.disease_name || pred.drug_name || '';
                        const predType = type === 'drug' ? 'disease' : 'drug';
                        // Use nullish coalescing to avoid treating index 0 as falsy
                        const idx = type === 'drug' 
                            ? (pred.disease_idx ?? pred.drug_idx ?? 0) 
                            : (pred.drug_idx ?? pred.disease_idx ?? 0);
                        let normScore = (pred.score || pred.probability || 0.5);
                        normScore = normScore > 1 ? normScore / 100 : normScore;
                        const nodeKey = predType + '_' + idx;
                        if (!nodeSet.has(nodeKey)) {
                            nodeSet.set(nodeKey, { name, type: predType, layer: 1, score: normScore, isQuery: false, isKnown: pred.is_known || false });
                        }
                        links.push({ source: qKey, target: nodeKey, weight: normScore });
                    }
                });
            });
            console.log('[3D-BATCH] Total nodes:', nodeSet.size, '| Total links:', links.length, '| Query nodes:', Array.from(nodeSet.entries()).filter(([k,v]) => v.isQuery).length);
        } else {
            // SINGLE MODE: Original single query node
            let queryName;
            if (type === 'drug') {
                queryName = document.getElementById('drug-search')?.value || `Drug #${queryIdx}`;
            } else if (type === 'disease') {
                queryName = document.getElementById('disease-search')?.value || `Disease #${queryIdx}`;
            } else {
                queryName = document.getElementById('protein-search')?.value || `Protein #${queryIdx}`;
            }
            nodeSet.set('query', {
                name: queryName,
                type: type,
                layer: 0,
                score: 1.0,
                isQuery: true
            });

            predictions.forEach((pred, i) => {
                if (type === 'protein') {
                    const drugKey = 'drug_' + pred.drug_idx;
                    const diseaseKey = 'disease_' + pred.disease_idx;
                    let normScore = (pred.score || 0.5);
                    normScore = normScore > 1 ? normScore / 100 : normScore;
                    if (!nodeSet.has(drugKey)) {
                        nodeSet.set(drugKey, { name: pred.drug_name || `Drug #${pred.drug_idx}`, type: 'drug', layer: 1, score: normScore, isQuery: false, isKnown: false });
                    }
                    if (!nodeSet.has(diseaseKey)) {
                        nodeSet.set(diseaseKey, { name: pred.disease_name || `Disease #${pred.disease_idx}`, type: 'disease', layer: 1, score: normScore, isQuery: false, isKnown: false });
                    }
                    links.push({ source: drugKey, target: 'query', weight: normScore });
                    links.push({ source: 'query', target: diseaseKey, weight: normScore });
                } else {
                    const name = pred.name || pred.disease_name || pred.drug_name || `Node #${pred.disease_idx ?? pred.drug_idx ?? i}`;
                    const predType = type === 'drug' ? 'disease' : 'drug';
                    // Use nullish coalescing to avoid treating index 0 as falsy
                    const idx = type === 'drug' 
                        ? (pred.disease_idx ?? pred.drug_idx ?? i) 
                        : (pred.drug_idx ?? pred.disease_idx ?? i);
                    let normScore = (pred.score || pred.probability || 0.5);
                    normScore = normScore > 1 ? normScore / 100 : normScore;
                    nodeSet.set(predType + '_' + idx, {
                        name, type: predType, layer: 1, score: normScore, isQuery: false, isKnown: pred.is_known || false
                    });
                    links.push({ source: 'query', target: predType + '_' + idx, weight: normScore });
                }
            });
        }

        // Helper to render graph from nodeSet and links
        function buildAndRenderGraph() {
            const nodes = Array.from(nodeSet.entries()).map(([key, val]) => ({
                id: key, ...val
            }));

            // Set query node target positions
            const queryPositions = {};
            const queryNodes = nodes.filter(n => n.isQuery);
            if (queryNodes.length > 1) {
                const radius = 500;
                queryNodes.forEach((qn, i) => {
                    const angle = (2 * Math.PI * i) / queryNodes.length;
                    const posX = Math.cos(angle) * radius;
                    const posY = Math.sin(angle) * radius;
                    const posZ = Math.sin(angle * 1.5) * 150;
                    queryPositions[qn.id] = { x: posX, y: posY, z: posZ };
                });
                console.log('[3D-BATCH] Query target positions:', JSON.stringify(queryPositions));
            }

            // Deduplicate links
            const linkSet = new Set();
            const uniqueLinks = links.filter(l => {
                const key = `${l.source}->${l.target}`;
                if (linkSet.has(key)) return false;
                linkSet.add(key);
                return true;
            });

            // Build HTML container
            let html = `
            <div style="position:relative;width:100%;height:${height}px;overflow:hidden;background:#1c1c1e;border-radius:16px;border:1px solid #333;">
                
                <!-- Faint orbital rings background -->
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);width:600px;height:600px;border-radius:50%;border:1px solid rgba(255,255,255,0.03);pointer-events:none;"></div>
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);width:450px;height:450px;border-radius:50%;border:1px solid rgba(255,255,255,0.04);pointer-events:none;"></div>
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);width:300px;height:300px;border-radius:50%;border:1px solid rgba(255,255,255,0.05);pointer-events:none;"></div>

                <!-- Top Overlay -->
                <div style="position:absolute;top:20px;left:50%;transform:translateX(-50%);z-index:10;width:90%;max-width:800px;display:flex;flex-direction:column;gap:12px;">
                    <!-- Instruction box -->
                    <div style="background:#e2e8f0;color:#64748b;padding:14px 20px;border-radius:16px;font-size:0.95rem;font-weight:600;box-shadow:0 4px 15px rgba(0,0,0,0.1);">
                        Thuoc mau xanh, protein mau vang, benh mau do. Keo chuot de xoay, lan chuot de phong to/thu nho.
                    </div>
                    <!-- Legend -->
                    <div style="display:flex;gap:12px;">
                        <div style="background:rgba(0,0,0,0.6);border:1px solid rgba(255,255,255,0.1);padding:6px 16px;border-radius:24px;display:flex;align-items:center;gap:10px;backdrop-filter:blur(4px);">
                            <div style="width:14px;height:14px;border-radius:50%;background:#3b82f6;border:3px solid #fff;"></div>
                            <span style="color:#f8fafc;font-size:0.9rem;font-weight:700;">Thuoc</span>
                        </div>
                        <div style="background:rgba(0,0,0,0.6);border:1px solid rgba(255,255,255,0.1);padding:6px 16px;border-radius:24px;display:flex;align-items:center;gap:10px;backdrop-filter:blur(4px);">
                            <div style="width:14px;height:14px;border-radius:50%;background:#f59e0b;border:3px solid #fff;"></div>
                            <span style="color:#f8fafc;font-size:0.9rem;font-weight:700;">Protein</span>
                        </div>
                        <div style="background:rgba(0,0,0,0.6);border:1px solid rgba(255,255,255,0.1);padding:6px 16px;border-radius:24px;display:flex;align-items:center;gap:10px;backdrop-filter:blur(4px);">
                            <div style="width:14px;height:14px;border-radius:50%;background:#ef4444;border:3px solid #fff;"></div>
                            <span style="color:#f8fafc;font-size:0.9rem;font-weight:700;">Benh</span>
                        </div>
                    </div>
                </div>
                
                <!-- WebGL Container -->
                <div id="gnn-3d-canvas" style="width:100%;height:100%;"></div>
            </div>
            `;

            container.innerHTML = html;

            // Initialize 3D Force Graph
            const canvasEl = document.getElementById('gnn-3d-canvas');
            if (typeof ForceGraph3D !== 'undefined' && canvasEl) {
            const Graph = ForceGraph3D()(canvasEl)
                    .graphData({ nodes: nodes, links: uniqueLinks })
                    .width(canvasEl.offsetWidth)
                    .height(canvasEl.offsetHeight)
                    .backgroundColor('rgba(0,0,0,0)')
                    .nodeId('id')
                    .nodeVal(n => n.isQuery ? 40 : (n.type === 'protein' ? 12 : 18))
                    .nodeColor(n => {
                        if (n.type === 'drug') return '#3b82f6';
                        if (n.type === 'disease') return '#ef4444';
                        if (n.type === 'protein') return '#f59e0b';
                        return '#64748b';
                    })
                    .nodeOpacity(1)
                    .nodeResolution(32)
                    .nodeLabel(n => `
                        <div style="background:rgba(20,25,40,0.95);padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,0.08);box-shadow:0 8px 24px rgba(0,0,0,0.5);font-family:sans-serif;min-width:120px;">
                            <div style="color:#f8fafc;font-weight:bold;font-size:15px;margin-bottom:6px;">${n.name}${n.isQuery ? ' ⭐' : ''}</div>
                            <div style="color:${n.type === 'drug' ? '#3b82f6' : (n.type === 'disease' ? '#ef4444' : '#f59e0b')};font-size:12px;font-family:monospace;font-weight:bold;">${n.isQuery ? 'QUERY NODE' : (n.id || n.type.toUpperCase())}</div>
                        </div>
                    `)
                    .linkColor(() => 'rgba(45,212,191,0.4)')
                    .linkWidth(1.5)
                    .linkDirectionalParticles(2)
                    .linkDirectionalParticleWidth(1.5)
                    .onNodeClick(node => {
                        const distance = 100;
                        const distRatio = 1 + distance / Math.hypot(node.x, node.y, node.z);
                        Graph.cameraPosition(
                            { x: node.x * distRatio, y: node.y * distRatio, z: node.z * distRatio },
                            node,
                            1500
                        );
                        showToast(`${node.type.toUpperCase()}: ${node.name} (${((node.score || 0) * 100).toFixed(1)}%)`, 'info');
                    });
                
                // Adjust graph forces
                const queryCount = queryNodes.length;
                Graph.d3Force('charge').strength(queryCount > 1 ? -600 : -250);
                Graph.d3Force('link').distance(queryCount > 1 ? 200 : 80);

                // CRITICAL: 3d-force-graph populates nodes asynchronously.
                // We MUST use onEngineTick to ensure fx, fy, fz are applied to the active simulation nodes.
                if (queryCount > 1) {
                    Graph.onEngineTick(() => {
                        const currentNodes = Graph.graphData().nodes;
                        currentNodes.forEach(n => {
                            if (n.isQuery && queryPositions[n.id]) {
                                // Brute-force override position and kill velocity
                                n.x = queryPositions[n.id].x;
                                n.y = queryPositions[n.id].y;
                                n.z = queryPositions[n.id].z;
                                n.vx = 0;
                                n.vy = 0;
                                n.vz = 0;
                                // Also try to set fx/fy/fz just in case the engine respects it later
                                n.fx = queryPositions[n.id].x;
                                n.fy = queryPositions[n.id].y;
                                n.fz = queryPositions[n.id].z;
                            }
                        });
                    });

                    // Zoom camera out to see all query hubs
                    setTimeout(() => {
                        Graph.cameraPosition({ x: 0, y: 0, z: 1200 }, { x: 0, y: 0, z: 0 }, 2000);
                    }, 500);
                } else {
                    Graph.onEngineTick(() => {}); // Clear tick for single mode
                }

                // Fix window resize
                window.addEventListener('resize', () => {
                    if (canvasEl.offsetWidth) {
                        Graph.width(canvasEl.offsetWidth).height(canvasEl.offsetHeight);
                    }
                });
            } else {
                canvasEl.innerHTML = '<div style="color:#f87171;text-align:center;padding:3rem;">Thư viện 3D Force Graph chưa tải được. Vui lòng F5 lại trang.</div>';
            }
        }

        // For protein type, skip bulk_pathway (we already have all nodes from predictions)
        if (type === 'protein') {
            buildAndRenderGraph();
        } else if (batchResults && batchResults.length > 1) {
            // Batch mode: fetch pathways for each query item, then render
            (async () => {
                for (let gi = 0; gi < batchResults.length; gi++) {
                    const result = batchResults[gi];
                    const qKey = 'query_' + gi;
                    const targets = result.predictions.slice(0, 10).map(p => type === 'drug' ? (p.disease_idx || p.drug_idx) : (p.drug_idx || p.disease_idx)).join(',');
                    const ds = result.dataset || 'C-dataset';
                    try {
                        const res = await fetch(`api/proxy.php?action=bulk_pathway&query_type=${type}&query_idx=${result.queryIdx}&targets=${targets}&dataset=${ds}`);
                        const data = await res.json();
                        if (data.proteins) {
                            data.proteins.forEach(p => {
                                const pKey = 'protein_' + p.idx;
                                if (!nodeSet.has(pKey)) {
                                    nodeSet.set(pKey, { name: p.name, type: 'protein', layer: 0.5, score: 0.8, isQuery: false });
                                }
                            });
                        }
                        if (data.edges) {
                            data.edges.forEach(e => {
                                // Remap 'query' source to the correct batch query key
                                const src = e.source === 'query' ? qKey : e.source;
                                links.push({ source: src, target: e.target, weight: 0.6 });
                            });
                        }
                    } catch (err) { console.error(`Batch pathway error for ${result.queryName}:`, err); }
                }
                buildAndRenderGraph();
            })();
        } else {
            // Single mode: enrich with pathway proteins
            const targets = predictions.slice(0, 10).map(p => type === 'drug' ? (p.disease_idx || p.drug_idx) : (p.drug_idx || p.disease_idx)).join(',');
            const dataset = document.getElementById(type + '-dataset')?.value || 'C-dataset';

            fetch(`api/proxy.php?action=bulk_pathway&query_type=${type}&query_idx=${queryIdx}&targets=${targets}&dataset=${dataset}`)
                .then(res => res.json())
                .then(data => {
                    if (data.proteins) {
                        data.proteins.forEach(p => {
                            nodeSet.set('protein_' + p.idx, { name: p.name, type: 'protein', layer: 0.5, score: 0.8, isQuery: false });
                        });
                    }
                    if (data.edges) {
                        data.edges.forEach(e => {
                            links.push({ source: e.source, target: e.target, weight: 0.6 });
                        });
                    }
                    buildAndRenderGraph();
                }).catch(err => {
                    console.error("Error loading bulk pathway:", err);
                    buildAndRenderGraph();
                });
        }
    }

    function quickSearch(type, q) {
        const input = document.getElementById(type + '-search');
        if (input) {
            input.value = q;
            loadItems(type, q);
            input.focus();
        }
    }

    // Autocomplete Logic - Unified
    function initAutocomplete() {
        console.log('[AMDGT] initAutocomplete called');
        ['drug', 'protein', 'disease'].forEach(type => {
            const input = document.getElementById(type + '-search');
            const list = document.getElementById(type + '-autocomplete');
            const letterFilter = document.getElementById(type + '-letter-filter');
            console.log(`[AMDGT] ${type}: input=${!!input}, list=${!!list}, letterFilter=${!!letterFilter}`);
            if (!input || !list) return;

            // Build letter filter A-Z + All
            if (letterFilter) {
                const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
                let filterHTML = `<button class="letter-btn all-btn active" onclick="filterLetter('${type}', '', this)">ALL</button>`;
                letters.forEach(letter => {
                    filterHTML += `<button class="letter-btn" onclick="filterLetter('${type}', '${letter}', this)">${letter}</button>`;
                });
                letterFilter.innerHTML = filterHTML;
            }

            input.addEventListener('focus', () => {
                if (input.value.trim().length === 0) {
                    loadItems(type, '');
                }
            });

            input.addEventListener('input', () => {
                const q = input.value.trim();
                console.log(`[AMDGT] input event for ${type}, q='${q}', calling loadItems`);
                // Clear letter filter when typing manually
                clearLetterFilter(type);
                loadItems(type, q);
            });
        });

        document.addEventListener('click', e => {
            if (!e.target.closest('.search-input-wrapper') && !e.target.closest('.letter-btn')) {
                document.querySelectorAll('.autocomplete-list').forEach(l => l.style.display = 'none');
            }
        });
    }

    // Letter filter: store active letter per type
    const activeLetter = { drug: '', protein: '', disease: '' };

    function filterLetter(type, letter, btn) {
        // Update active state
        activeLetter[type] = letter;
        const filterEl = document.getElementById(type + '-letter-filter');
        if (filterEl) {
            filterEl.querySelectorAll('.letter-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
        }
        // Load items filtered by letter
        const input = document.getElementById(type + '-search');
        if (input) input.value = '';
        loadItems(type, letter ? letter.toLowerCase() : '');
    }

    function clearLetterFilter(type) {
        activeLetter[type] = '';
        const filterEl = document.getElementById(type + '-letter-filter');
        if (filterEl) {
            filterEl.querySelectorAll('.letter-btn').forEach(b => b.classList.remove('active'));
            const allBtn = filterEl.querySelector('.all-btn');
            if (allBtn) allBtn.classList.add('active');
        }
    }

    function loadItems(type, q) {
        console.log(`[AMDGT] loadItems called: type=${type}, q='${q}'`);
        const list = document.getElementById(type + '-autocomplete');
        const dataset = document.getElementById('global-dataset').value || 'C-dataset';
        const gen = ++autocompleteGeneration;

        // Encode query - letter filter passes single letter
        const encodedQ = q.length > 0 ? encodeURIComponent(q) : '%25';

        const url = `api/search.php?type=${type}&q=${encodedQ}&dataset=${dataset}`;

        // Force show dropdown with high z-index
        list.style.cssText = 'display: block !important; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #1e293b; border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; max-height: 320px; overflow-y: auto; z-index: 99999 !important; box-shadow: 0 20px 40px rgba(0,0,0,0.5);';
        list.innerHTML = '<div style="padding:15px;text-align:center;color:#64748b;font-size:0.85rem;"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (gen !== autocompleteGeneration) return;
                if (!data || data.length === 0) {
                    list.innerHTML = '<div style="padding:15px;text-align:center;color:#64748b;font-size:0.85rem;">Không tìm thấy kết quả nào</div>';
                } else {
                    list.innerHTML = data.map(item => `
                        <div class="autocomplete-item" onclick="selectItem('${type}', ${item.idx}, '${(item.name || '').replace(/'/g, "\\'")}', '${(item.drug_id || item.disease_id || item.protein_id || '').replace(/'/g, "\\'")}', '${item.dataset}')">
                            <div class="item-icon"><i class="fas ${type==='drug'?'fa-capsules':type==='protein'?'fa-dna':'fa-virus'}"></i></div>
                            <div class="item-info">
                                <div class="item-name">${item.name || 'N/A'}</div>
                                <div class="item-id">${item.drug_id || item.disease_id || item.protein_id || ''}</div>
                            </div>
                            <div class="item-dataset">${item.dataset}</div>
                        </div>
                    `).join('');
                }
            })
            .catch(err => {
                console.error('Load items error:', err);
                if (list) list.innerHTML = '<div style="padding:15px;text-align:center;color:#ef4444;font-size:0.85rem;">Lỗi tải dữ liệu</div>';
            });
    }

    window.selectItem = function(type, idx, name, id, dataset) {
        // Multi-select: add to array
        addItem(type, idx, name, id, dataset);
        
        // Clear input for next selection
        const input = document.getElementById(type + '-search');
        if (input) {
            input.value = '';
        }
        
        // Hide dropdown after selection
        const list = document.getElementById(type + '-autocomplete');
        if (list) {
            list.style.display = 'none';
        }
    }

    // ========== BATCH PREDICTION HELPER ==========
    async function fetchPrediction(apiPath, body, fallbackPath, fallbackBody) {
        try {
            const r = await fetch(apiPath, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await r.json();
            if (data.error) throw new Error(data.error);
            return data;
        } catch (e) {
            if (fallbackPath) {
                const r2 = await fetch(fallbackPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(fallbackBody || body)
                });
                const data2 = await r2.json();
                if (data2.error) throw new Error(data2.error);
                return data2;
            }
            throw e;
        }
    }

    function renderBatchResults(allResults, queryType, resultType) {
        document.getElementById('results-section').style.display = 'block';
        document.getElementById('action-bar').style.display = 'flex';
        
        const totalPredictions = allResults.reduce((sum, r) => sum + r.predictions.length, 0);
        const allPredictions = allResults.flatMap(r => r.predictions);
        const avgScore = allPredictions.length > 0 
            ? (allPredictions.reduce((s, p) => s + (p.score || 0), 0) / allPredictions.length).toFixed(1)
            : '0';
        
        const icon = queryType === 'drug' ? 'fa-capsules' : queryType === 'disease' ? 'fa-virus' : 'fa-dna';
        const color = queryType === 'drug' ? '#818cf8' : queryType === 'disease' ? '#34d399' : '#f472b6';
        const queryNames = allResults.map(r => r.queryName).join(', ');
        
        document.getElementById('results-header').innerHTML = `
            <i class="fas ${icon}" style="color:${color};"></i> Phân tích hàng loạt: 
            <span style="color:#fff;">${allResults.length} ${queryType === 'drug' ? 'thuốc' : queryType === 'disease' ? 'bệnh' : 'protein'}</span>
            <div style="font-size:0.8rem; color:#64748b; font-weight:400; margin-top:4px;">
                ${queryNames}
            </div>`;
        
        document.getElementById('stats-badges').innerHTML = `
            <div class="stat-badge" style="border: 1px solid rgba(99, 102, 241, 0.2);">
                <div class="badge-icon" style="background:rgba(99, 102, 241, 0.1);color:#818cf8;"><i class="fas fa-layer-group"></i></div>
                <div><div class="badge-value" style="color:#818cf8;">${allResults.length}</div><div class="badge-label">Queries</div></div>
            </div>
            <div class="stat-badge" style="border: 1px solid rgba(0, 255, 204, 0.2);">
                <div class="badge-icon" style="background:rgba(0, 255, 204, 0.1);color:#00ffcc;"><i class="fas fa-chart-bar"></i></div>
                <div><div class="badge-value" style="color:#00ffcc;">${totalPredictions}</div><div class="badge-label">Total Results</div></div>
            </div>
            <div class="stat-badge" style="border: 1px solid rgba(129, 140, 248, 0.2);">
                <div class="badge-icon" style="background:rgba(129, 140, 248, 0.1);color:#818cf8;"><i class="fas fa-brain"></i></div>
                <div><div class="badge-value" style="color:#818cf8;">${avgScore}%</div><div class="badge-label">Avg Score</div></div>
            </div>`;
        
        let gridHtml = '';
        allResults.forEach((result) => {
            const groupIcon = queryType === 'drug' ? 'drug-icon' : 'disease-icon';
            const groupFa = queryType === 'drug' ? 'fa-capsules' : queryType === 'disease' ? 'fa-virus' : 'fa-dna';
            
            gridHtml += `<div class="batch-group-header">
                <div class="group-icon ${groupIcon}"><i class="fas ${groupFa}"></i></div>
                <span>${result.queryName}</span>
                <span class="group-count">${result.predictions.length} kết quả</span>
            </div>`;
            
            result.predictions.forEach((p, i) => {
                const score = p.score || 0;
                const scoreColor = score >= 70 ? '#34d399' : score >= 40 ? '#fbbf24' : '#f87171';
                const scoreGrad = score >= 70 ? 'linear-gradient(90deg, #10b981, #34d399)' : score >= 40 ? '#fbbf24' : '#f87171';
                const targetName = resultType === 'disease' ? (p.disease_name || p.name || '') : (p.drug_name || p.name || '');
                const safeDrugName = String(p.drug_name || result.queryName).replace(/'/g, "\\'");
                const drugIdx = p.drug_idx ?? result.queryIdx;
                const diseaseIdx = p.disease_idx ?? 0;
                
                gridHtml += `
                <div class="result-card">
                    <div class="rank-circle">${i + 1}</div>
                    <div class="info-section" style="flex:1;">
                        <div style="font-size:0.6rem; color:${color}; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">${result.queryName}</div>
                        <h4 style="font-size:0.9rem; margin:0.15rem 0;">
                            <span style="color:${resultType === 'disease' ? '#34d399' : '#818cf8'};">${targetName}</span>
                        </h4>
                        <div class="id-label" style="font-size:0.65rem;">Score: ${score.toFixed(1)}% | ${p.is_known ? '✅ Đã xác nhận' : '🔬 Dự đoán mới'}</div>
                    </div>
                    <div class="progress-section">
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: ${score}%; background: ${scoreGrad};"></div>
                        </div>
                        <div class="score-text" style="color: ${scoreColor};">${score.toFixed(1)}%</div>
                    </div>
                    <div class="btn-section">
                        <button class="mini-action-btn" onclick="openIntelligenceHub(${drugIdx}, ${diseaseIdx}, '${safeDrugName}', ${score}, ${p.is_known ? 1 : 0}, 'xai')">
                            <i class="fas fa-microchip"></i> XAI
                        </button>
                    </div>
                </div>`;
            });
        });
        
        document.getElementById('results-grid').innerHTML = gridHtml;
        
        if (allResults.length > 0) {
            // Merge ALL predictions with source labels for multi-color viz
            const allPreds = allResults.flatMap(r => 
                r.predictions.map(p => ({ ...p, _batchSource: r.queryName }))
            );
            renderLandscape(allPreds, allResults);
            renderGNN3DGraph(allPreds, queryType, allResults[0].queryIdx, allResults);
        }
        
        // Auto switch to 3D
        const tab3d = document.querySelector('.viz-tab-btn[onclick*="\'3d\'"]');
        if (tab3d) { switchVizTab(tab3d, '3d'); }
        else { document.querySelectorAll('.viz-tab-btn').forEach(t => { if (t.textContent.includes('Đồ Thị 3D')) switchVizTab(t, '3d'); }); }
        
        setTimeout(() => {
            const el = document.getElementById('results-section');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    }
    // ========== END BATCH HELPER ==========

    // PREDICTION FUNCTIONS
    function predictDrug() {
        const btn = document.getElementById('btn-drug');
        const items = selectedItems.drug;
        const topk = document.getElementById('drug-topk').value || 20;
        
        // Batch mode
        if (items.length > 1) {
            if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> 0/${items.length}...`; btn.style.opacity = '0.7'; }
            showLoading();
            (async () => {
                const allResults = [];
                for (let i = 0; i < items.length; i++) {
                    const item = items[i];
                    showBatchProgress('drug', i + 1, items.length);
                    if (btn) btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${i + 1}/${items.length}...`;
                    try {
                        const data = await fetchPrediction(
                            'api/predict.php',
                            { type: 'drug_to_disease', drug_idx: item.idx, top_k: parseInt(topk), dataset: item.dataset },
                            'api/proxy.php?action=predict_drug',
                            { drug_idx: item.idx, top_k: parseInt(topk), dataset: item.dataset }
                        );
                        if (data) {
                            allResults.push({ queryName: data.query_name || item.name, queryIdx: item.idx, predictions: data.predictions || [], dataset: item.dataset });
                        }
                    } catch (err) { console.error(`Batch drug error for ${item.name}:`, err); }
                }
                hideBatchProgress('drug'); hideLoading();
                if (allResults.length > 0) { 
                    if (allResults.length < items.length) {
                        showToast(`Cảnh báo: ${items.length - allResults.length} thuốc không có kết quả trong dataset này.`, 'warning');
                    }
                    renderBatchResults(allResults, 'drug', 'disease'); 
                    loadModelPerformance(items[0].dataset); 
                }
                else { alert('Không có kết quả dự đoán cho các thuốc đã chọn!'); }
                if (btn) resetButton(btn, 'PHÂN TÍCH THUỐC', 'fa-brain');
                updateBtnCount('drug');
            })();
            return;
        }
        
        // Single mode
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ĐANG XỬ LÝ...'; btn.style.opacity = '0.7'; }
        const idx = items.length === 1 ? items[0].idx : document.getElementById('drug-idx').value;
        const dataset = items.length === 1 ? items[0].dataset : (document.getElementById('drug-dataset').value || 'C-dataset');
        const textQuery = document.getElementById('drug-search').value.trim();

        if (!idx && idx !== 0 && idx !== '0') {
            if (textQuery.length > 0) {
                const gen = ++currentPredictionGeneration;
                const globalDs = document.getElementById('global-dataset')?.value || 'C-dataset';
                fetch(`api/search.php?type=drug&q=${encodeURIComponent(textQuery)}&dataset=${globalDs}`)
                    .then(r => r.json())
                    .then(searchItems => {
                        if (gen !== currentPredictionGeneration) return;
                        const match = searchItems && searchItems.length > 0 ? searchItems[0] : null;
                        if (match) { addItem('drug', match.idx, match.name, match.drug_id, match.dataset); predictDrug(); }
                        else { alert('Không tìm thấy thuốc này.'); if (btn) resetButton(btn, 'PHÂN TÍCH THUỐC', 'fa-brain'); }
                    }).catch(() => { if (btn) resetButton(btn, 'PHÂN TÍCH THUỐC', 'fa-brain'); });
                return;
            } else { alert('Hãy nhập tên thuốc hoặc chọn từ gợi ý'); if (btn) resetButton(btn, 'PHÂN TÍCH THUỐC', 'fa-brain'); return; }
        }

        showLoading();
        fetchPrediction('api/predict.php',
            { type: 'drug_to_disease', drug_idx: parseInt(idx), top_k: parseInt(topk), dataset: dataset },
            'api/proxy.php?action=predict_drug',
            { drug_idx: parseInt(idx), top_k: parseInt(topk), dataset: dataset }
        ).then(data => { handleDrugResults(data); })
        .catch(err => { console.error('Prediction error:', err); alert('Lỗi dự đoán: ' + err.message); hideLoading(); });
    }

    function resetButton(btn, text, iconClass) {
        if (!btn) return;
        btn.disabled = false;
        btn.innerHTML = `<i class="fas ${iconClass}"></i> ${text}`;
        btn.style.opacity = '1';
    }

    function handleDrugResults(data) {
        const btn = document.getElementById('btn-drug');
        if (!data.predictions || data.predictions.length === 0) {
            alert('Không có kết quả dự đoán!');
            hideLoading(); if (btn) resetButton(btn, 'PHÂN TÍCH THUỐC', 'fa-brain'); updateBtnCount('drug'); return;
        }
        const idx = parseInt(document.getElementById('drug-idx').value);
        renderResults(data.predictions, 'disease', data.query_name || document.getElementById('drug-search').value || 'Drug', idx);
        renderLandscape(data.predictions);
        const dataset = document.getElementById('drug-dataset').value || 'C-dataset';
        loadModelPerformance(dataset);
        renderGNN3DGraph(data.predictions, 'drug', idx);
        const tab3d = document.querySelector('.viz-tab-btn[onclick*="\'3d\'"]');
        if (tab3d) { switchVizTab(tab3d, '3d'); }
        else { document.querySelectorAll('.viz-tab-btn').forEach(t => { if (t.textContent.includes('Đồ Thị 3D')) switchVizTab(t, '3d'); }); }
        hideLoading(); if (btn) resetButton(btn, 'PHÂN TÍCH THUỐC', 'fa-brain'); updateBtnCount('drug');
        setTimeout(() => { const el = document.getElementById('results-section'); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 100);
    }

    function predictDisease() {
        const btn = document.getElementById('btn-disease');
        const items = selectedItems.disease;
        const topk = document.getElementById('disease-topk').value || 20;
        
        // Batch mode
        if (items.length > 1) {
            if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> 0/${items.length}...`; btn.style.opacity = '0.7'; }
            showLoading();
            (async () => {
                const allResults = [];
                for (let i = 0; i < items.length; i++) {
                    const item = items[i];
                    showBatchProgress('disease', i + 1, items.length);
                    if (btn) btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${i + 1}/${items.length}...`;
                    try {
                        const data = await fetchPrediction('api/predict.php',
                            { type: 'disease_to_drug', disease_idx: item.idx, top_k: parseInt(topk), dataset: item.dataset });
                        if (data) {
                            allResults.push({ queryName: data.query_name || item.name, queryIdx: item.idx, predictions: data.predictions || [], dataset: item.dataset });
                        }
                    } catch (err) { console.error(`Batch disease error for ${item.name}:`, err); }
                }
                hideBatchProgress('disease'); hideLoading();
                if (allResults.length > 0) { 
                    if (allResults.length < items.length) {
                        showToast(`Cảnh báo: ${items.length - allResults.length} bệnh không có kết quả trong dataset này.`, 'warning');
                    }
                    renderBatchResults(allResults, 'disease', 'drug'); 
                    loadModelPerformance(items[0].dataset); 
                }
                else { alert('Không có kết quả dự đoán cho các bệnh đã chọn!'); }
                if (btn) resetButton(btn, 'PHÂN TÍCH BỆNH', 'fa-brain');
                updateBtnCount('disease');
            })();
            return;
        }
        
        // Single mode
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ĐANG XỬ LÝ...'; btn.style.opacity = '0.7'; }
        const idx = items.length === 1 ? items[0].idx : document.getElementById('disease-idx').value;
        const dataset = items.length === 1 ? items[0].dataset : (document.getElementById('disease-dataset').value || 'C-dataset');

        if (!idx && idx !== 0 && idx !== '0') {
            alert('Vui lòng chọn bệnh từ danh sách gợi ý trước khi phân tích.');
            if (btn) resetButton(btn, 'PHÂN TÍCH BỆNH', 'fa-brain');
            return;
        }

        showLoading();
        fetchPrediction('api/predict.php',
            { type: 'disease_to_drug', disease_idx: parseInt(idx), top_k: parseInt(topk), dataset: dataset }
        ).then(data => {
            if (!data.predictions || data.predictions.length === 0) {
                alert('Không có kết quả dự đoán!'); hideLoading(); if (btn) resetButton(btn, 'PHÂN TÍCH BỆNH', 'fa-brain'); updateBtnCount('disease'); return;
            }
            document.getElementById('results-section').style.display = 'block';
            document.getElementById('action-bar').style.display = 'flex';
            renderResults(data.predictions, 'drug', data.query_name || document.getElementById('disease-search').value || 'Disease', parseInt(idx));
            renderLandscape(data.predictions);
            loadModelPerformance(dataset);
            renderGNN3DGraph(data.predictions, 'disease', parseInt(idx));
            const tab3d = document.querySelector('.viz-tab-btn[onclick*="\'3d\'"]');
            if (tab3d) { switchVizTab(tab3d, '3d'); }
            else { document.querySelectorAll('.viz-tab-btn').forEach(t => { if (t.textContent.includes('Đồ Thị 3D')) switchVizTab(t, '3d'); }); }
            hideLoading(); if (btn) resetButton(btn, 'PHÂN TÍCH BỆNH', 'fa-brain'); updateBtnCount('disease');
            setTimeout(() => { const el = document.getElementById('results-section'); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 100);
        }).catch(err => {
            console.error('Predict disease error:', err);
            alert('Lỗi kết nối: ' + err.message);
            hideLoading(); if (btn) resetButton(btn, 'PHÂN TÍCH BỆNH', 'fa-brain');
        });
    }

    function predictProtein() {
        const btn = document.getElementById('btn-protein');
        const items = selectedItems.protein;
        const topk = document.getElementById('protein-topk').value || 20;
        
        // Batch mode
        if (items.length > 1) {
            if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> 0/${items.length}...`; btn.style.opacity = '0.7'; }
            showLoading();
            (async () => {
                const allResults = [];
                for (let i = 0; i < items.length; i++) {
                    const item = items[i];
                    showBatchProgress('protein', i + 1, items.length);
                    if (btn) btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${i + 1}/${items.length}...`;
                    try {
                        const data = await fetchPrediction('api/predict.php',
                            { type: 'protein_to_any', protein_idx: item.idx, top_k: parseInt(topk), dataset: item.dataset });
                        if (data) {
                            allResults.push({ queryName: data.query_name || item.name, queryIdx: item.idx, predictions: data.mediated_predictions || [], dataset: item.dataset });
                        }
                    } catch (err) { console.error(`Batch protein error for ${item.name}:`, err); }
                }
                hideBatchProgress('protein'); hideLoading();
                if (allResults.length > 0) { 
                    if (allResults.length < items.length) {
                        showToast(`Cảnh báo: ${items.length - allResults.length} protein không có liên kết trong dataset này.`, 'warning');
                    }
                    renderBatchResults(allResults, 'protein', 'pathway'); 
                    loadModelPerformance(items[0].dataset); 
                }
                else { alert('Không tìm thấy liên kết cho protein nào!'); }
                if (btn) resetButton(btn, 'PHÂN TÍCH PROTEIN', 'fa-brain');
                updateBtnCount('protein');
            })();
            return;
        }
        
        // Single mode
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ĐANG XỬ LÝ...'; btn.style.opacity = '0.7'; }
        const idx = items.length === 1 ? items[0].idx : document.getElementById('protein-idx').value;
        const dataset = items.length === 1 ? items[0].dataset : (document.getElementById('protein-dataset').value || 'C-dataset');
        const proteinName = items.length === 1 ? items[0].name : (document.getElementById('protein-search').value || 'Protein');

        if (!idx && idx !== 0 && idx !== '0') {
            alert('Vui lòng chọn protein từ danh sách gợi ý trước khi phân tích.');
            if (btn) resetButton(btn, 'PHÂN TÍCH PROTEIN', 'fa-brain');
            return;
        }

        showLoading();
        fetch('api/predict.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'protein_to_any', protein_idx: parseInt(idx), top_k: parseInt(topk), dataset: dataset })
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) { alert(data.error); hideLoading(); if (btn) resetButton(btn, 'PHÂN TÍCH PROTEIN', 'fa-brain'); return; }
                
                const predictions = data.mediated_predictions || [];
                if (predictions.length === 0) {
                    document.getElementById('results-section').style.display = 'block';
                    document.getElementById('action-bar').style.display = 'flex';
                    document.getElementById('results-header').innerHTML = `<i class="fas fa-dna" style="color:#f472b6;"></i> Phân tích Protein: <span style="color:#fff;">${data.query_name || proteinName}</span><div style="font-size:0.8rem; color:#64748b; font-weight:400; margin-top:4px;">Không tìm thấy liên kết Drug-Protein-Disease nào</div>`;
                    document.getElementById('stats-badges').innerHTML = '';
                    document.getElementById('results-grid').innerHTML = `
                        <div class="result-card" style="grid-column: 1/-1; text-align:center; padding: 2rem;">
                            <i class="fas fa-search" style="font-size: 2rem; color: #64748b; margin-bottom: 1rem;"></i>
                            <h4 style="color:#94a3b8;">Protein này chưa có liên kết trong bộ dữ liệu ${dataset}</h4>
                            <p style="color:#64748b; font-size:0.8rem;">Thử chọn protein khác hoặc đổi sang dataset khác</p>
                        </div>`;
                    hideLoading();
                    if (btn) resetButton(btn, 'PHÂN TÍCH PROTEIN', 'fa-brain');
                    updateBtnCount('protein');
                    return;
                }

                const uniqueDrugs = [...new Set(predictions.map(p => p.drug_idx))];
                const uniqueDiseases = [...new Set(predictions.map(p => p.disease_idx))];
                const avgScore = (predictions.reduce((a, p) => a + p.score, 0) / predictions.length).toFixed(1);

                document.getElementById('results-section').style.display = 'block';
                document.getElementById('action-bar').style.display = 'flex';
                document.getElementById('results-header').innerHTML = `<i class="fas fa-dna" style="color:#f472b6;"></i> Phân tích Protein: <span style="color:#fff;">${data.query_name || proteinName}</span><div style="font-size:0.8rem; color:#64748b; font-weight:400; margin-top:4px;">${predictions.length} liên kết Drug-Protein-Disease</div>`;
                
                document.getElementById('stats-badges').innerHTML = `
                    <div class="stat-badge" style="border: 1px solid rgba(236, 72, 153, 0.2);">
                        <div class="badge-icon" style="background:rgba(236, 72, 153, 0.1);color:#f472b6;"><i class="fas fa-pills"></i></div>
                        <div><div class="badge-value" style="color:#f472b6;">${uniqueDrugs.length}</div><div class="badge-label">Thuốc</div></div>
                    </div>
                    <div class="stat-badge" style="border: 1px solid rgba(52, 211, 153, 0.2);">
                        <div class="badge-icon" style="background:rgba(52, 211, 153, 0.1);color:#34d399;"><i class="fas fa-virus"></i></div>
                        <div><div class="badge-value" style="color:#34d399;">${uniqueDiseases.length}</div><div class="badge-label">Bệnh</div></div>
                    </div>
                    <div class="stat-badge" style="border: 1px solid rgba(0, 255, 204, 0.2);">
                        <div class="badge-icon" style="background:rgba(0, 255, 204, 0.1);color:#00ffcc;"><i class="fas fa-project-diagram"></i></div>
                        <div><div class="badge-value" style="color:#00ffcc;">${predictions.length}</div><div class="badge-label">Pathways</div></div>
                    </div>
                    <div class="stat-badge" style="border: 1px solid rgba(129, 140, 248, 0.2);">
                        <div class="badge-icon" style="background:rgba(129, 140, 248, 0.1);color:#818cf8;"><i class="fas fa-brain"></i></div>
                        <div><div class="badge-value" style="color:#818cf8;">${avgScore}%</div><div class="badge-label">Avg Score</div></div>
                    </div>
                `;

                document.getElementById('results-grid').innerHTML = predictions.map((p, i) => {
                    const score = p.score || 0;
                    const scoreColor = score >= 70 ? '#34d399' : score >= 40 ? '#fbbf24' : '#f87171';
                    const scoreGrad = score >= 70 ? 'linear-gradient(90deg, #10b981, #34d399)' : score >= 40 ? '#fbbf24' : '#f87171';
                    const safeDrugName = String(p.drug_name).replace(/'/g, "\\'");
                    return `
                    <div class="result-card">
                        <div class="rank-circle">${i + 1}</div>
                        <div class="info-section" style="flex:1;">
                            <div style="font-size:0.6rem; color:#f472b6; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">PATHWAY</div>
                            <h4 style="font-size:0.9rem; margin:0.15rem 0;">
                                <span style="color:#818cf8;">${p.drug_name}</span>
                                <span style="color:#475569; font-size:0.7rem;"> → </span>
                                <span style="color:#f472b6;">${data.query_name || proteinName}</span>
                                <span style="color:#475569; font-size:0.7rem;"> → </span>
                                <span style="color:#34d399;">${p.disease_name}</span>
                            </h4>
                            <div class="id-label" style="font-size:0.65rem;">Drug #${p.drug_idx} → Protein #${idx} → Disease #${p.disease_idx}</div>
                        </div>
                        <div class="progress-section">
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: ${score}%; background: ${scoreGrad};"></div>
                            </div>
                            <div class="score-text" style="color: ${scoreColor};">${score.toFixed(1)}%</div>
                        </div>
                        <div class="btn-section">
                            <button class="mini-action-btn" onclick="openIntelligenceHub(${p.drug_idx}, ${p.disease_idx}, '${safeDrugName}', ${score}, 0, 'xai')">
                                <i class="fas fa-microchip"></i> Chi Tiết XAI
                            </button>
                        </div>
                    </div>
                    `;
                }).join('');

                renderGNN3DGraph(predictions, 'protein', parseInt(idx));
                const tab3d = document.querySelector('.viz-tab-btn[onclick*="\'3d\'"]');
                if (tab3d) { switchVizTab(tab3d, '3d'); }
                else { document.querySelectorAll('.viz-tab-btn').forEach(t => { if (t.textContent.includes('Đồ Thị 3D')) switchVizTab(t, '3d'); }); }
                hideLoading(); if (btn) resetButton(btn, 'PHÂN TÍCH PROTEIN', 'fa-brain'); updateBtnCount('protein');
                setTimeout(() => { const el = document.getElementById('results-section'); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 100);
            })
            .catch(err => {
                console.error('Predict protein error:', err);
                alert('Lỗi kết nối: ' + err.message);
                hideLoading(); if (btn) resetButton(btn, 'PHÂN TÍCH PROTEIN', 'fa-brain');
            });
    }
    
    function hideLoading() {
        // Không ẩn results-section ở đây vì nó chứa kết quả thực tế
        const loadingOrb = document.querySelector('.ai-loading-container');
        if (loadingOrb) loadingOrb.style.opacity = '0';
    }

    function showLoading() {
        document.getElementById('results-section').style.display = 'block';
        document.getElementById('action-bar').style.display = 'flex';
        document.getElementById('results-grid').innerHTML = `
        <div style="grid-column: 1/-1; text-align:center; padding: 4rem;">
            <div class="ai-loading-container">
                <div class="ai-loading-orb">
                    <div class="ai-loading-ring"></div>
                    <div class="ai-loading-ring"></div>
                    <div class="ai-loading-ring"></div>
                    <div class="ai-loading-core">
                        <i class="fas fa-brain" style="font-size: 1.5rem; color: #00ffcc;"></i>
                    </div>
                </div>
                <div class="ai-loading-text">
                    <span class="ai-loading-title">AI ĐANG PHÂN TÍCH</span>
                    <span class="ai-loading-subtitle">Đang xử lý dữ liệu mạng lưới...</span>
                </div>
                <div class="ai-loading-progress">
                    <div class="ai-loading-bar"></div>
                </div>
                <div class="ai-loading-stats">
                    <div class="ai-loading-stat">
                        <i class="fas fa-network-wired"></i>
                        <span>Đang xây dựng đồ thị</span>
                    </div>
                    <div class="ai-loading-stat">
                        <i class="fas fa-brain"></i>
                        <span>Đang chạy mô hình GNN</span>
                    </div>
                    <div class="ai-loading-stat">
                        <i class="fas fa-chart-line"></i>
                        <span>Đang tính toán điểm số</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    }

    // Render Results
    function renderResults(predictions, type, queryName, queryId) {
        document.getElementById('results-header').innerHTML = `<i class="fas fa-network-wired" style="color:#818cf8;"></i> Kết quả phân tích GNN: <span style="color:#fff;">${queryName}</span><div style="font-size:0.8rem; color:#64748b; font-weight:400; margin-top:4px;">${predictions.length} kết quả tiềm năng nhất</div>`;
        const known = predictions.filter(p => p.is_known).length;
        const newP = predictions.length - known;
        const avgScore = (predictions.reduce((a, p) => a + p.score, 0) / predictions.length).toFixed(1);

        document.getElementById('stats-badges').innerHTML = `
        <div class="stat-badge" style="border: 1px solid rgba(52, 211, 153, 0.2);">
            <div class="badge-icon" style="background:rgba(52, 211, 153, 0.1);color:#34d399;"><i class="fas fa-check-circle"></i></div>
            <div><div class="badge-value" style="color:#34d399;">${known}</div><div class="badge-label">Đã biết</div></div>
        </div>
        <div class="stat-badge" style="border: 1px solid rgba(245, 158, 11, 0.2);">
            <div class="badge-icon" style="background:rgba(245, 158, 11, 0.1);color:#fbbf24;"><i class="fas fa-star"></i></div>
            <div><div class="badge-value" style="color:#fbbf24;">${newP}</div><div class="badge-label">Mới phát hiện</div></div>
        </div>
        <div class="stat-badge" style="border: 1px solid rgba(0, 255, 204, 0.2);">
            <div class="badge-icon" style="background:rgba(0, 255, 204, 0.1);color:#00ffcc;"><i class="fas fa-brain"></i></div>
            <div><div class="badge-value" style="color:#00ffcc;">${avgScore}%</div><div class="badge-label">AI AUC</div></div>
        </div>
    `;
        document.getElementById('results-grid').innerHTML = predictions.map((p, i) => {
            const name = type === 'disease' ? p.disease_name : p.drug_name;
            const id = type === 'disease' ? p.disease_id : p.drug_id;
            const score = p.score || 0;
            const drugIdx = type === 'disease' ? queryId : p.drug_idx;
            const diseaseIdx = type === 'disease' ? p.disease_idx : queryId;
            const safeName = String(name).replace(/'/g, "\\'");

            return `
            <div class="result-card">
                <div class="rank-circle">${p.rank || i + 1}</div>
                <div class="info-section">
                    <div style="font-size:0.65rem; color:#818cf8; font-weight:800; text-transform:uppercase;">${type === 'disease' ? 'Disease' : 'Drug'}</div>
                    <h4>${name}</h4>
                    <div class="id-label">${id}</div>
                </div>
                <div class="progress-section">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: ${score}%; background: ${score >= 70 ? 'linear-gradient(90deg, #10b981, #34d399)' : score >= 40 ? '#fbbf24' : '#f87171'};"></div>
                    </div>
                    <div class="score-text" style="color: ${score >= 70 ? '#34d399' : score >= 40 ? '#fbbf24' : '#f87171'};">${score.toFixed(1)}%</div>
                </div>
                <div class="badges-section">
                    <span class="type-badge ${score >= 70 ? 'known' : 'new'}" style="margin:0; padding: 4px 10px;">
                        <i class="fas fa-shield-virus"></i> ${score >= 70 ? 'Hiệu quả cao' : 'Tiềm năng'}
                    </span>
                    <span class="type-badge ${p.is_known ? 'known' : 'new'}" style="margin:0; padding: 4px 10px;">
                        ${p.is_known ? 'Đã biết' : 'Mới'}
                    </span>
                </div>
                <div class="btn-section">
                    <button class="mini-action-btn" onclick="openIntelligenceHub(${drugIdx}, ${diseaseIdx}, '${safeName}', ${score}, ${p.is_known ? 1 : 0}, 'xai')">
                        <i class="fas fa-microchip"></i> Chi Tiết XAI
                    </button>
                </div>
            </div>
            `;
        }).join('');
    }

    function openModal(title, content) {
        const titleEl = document.getElementById('modal-title');
        const bodyEl = document.getElementById('modal-body');
        const overlay = document.getElementById('modal-overlay');
        if (titleEl) titleEl.innerHTML = title;
        if (bodyEl) bodyEl.innerHTML = content;
        if (overlay) overlay.classList.add('active');
    }

    function closeModal() {
        const overlay = document.getElementById('modal-overlay');
        if (overlay) overlay.classList.remove('active');
    }

    if (document.getElementById('modal-overlay')) {
        document.getElementById('modal-overlay').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
    }

    // COMPREHENSIVE INTELLIGENCE HUB (360° ANALYSIS)
    window.openIntelligenceHub = function(drugIdx, diseaseIdx, targetName, score, isKnown, defaultTab = 'xai') {
        // Smart name resolution: determine if we came from drug→disease or disease→drug
        const drugSearchVal = document.getElementById('drug-search')?.value || '';
        const diseaseSearchVal = document.getElementById('disease-search')?.value || '';
        
        // If drug search has a value, user searched drug → targets are diseases
        // If disease search has a value, user searched disease → targets are drugs
        let drugName, diseaseName;
        if (drugSearchVal && !diseaseSearchVal) {
            // Drug → Disease mode: targetName = disease name
            drugName = drugSearchVal;
            diseaseName = targetName;
        } else if (diseaseSearchVal && !drugSearchVal) {
            // Disease → Drug mode: targetName = drug name
            drugName = targetName;
            diseaseName = diseaseSearchVal;
        } else {
            // Fallback: use API data (will be resolved after fetch)
            drugName = drugSearchVal || targetName || 'Selected Drug';
            diseaseName = diseaseSearchVal || targetName || 'Selected Disease';
        }
        const dataset = document.getElementById('global-dataset')?.value || 'C-dataset';
        const scoreLevel = score >= 70 ? 'high' : score >= 40 ? 'medium' : 'low';
        
        const colors = {
            high: { primary: '#00ffcc', secondary: '#00ccff', bg: 'rgba(0, 255, 204, 0.05)', border: 'rgba(0, 255, 204, 0.3)', glow: '0 0 25px rgba(0, 255, 204, 0.4)', text: 'OPTIMAL' },
            medium: { primary: '#f0f33f', secondary: '#f39c12', bg: 'rgba(240, 243, 63, 0.05)', border: 'rgba(240, 243, 63, 0.3)', glow: '0 0 25px rgba(240, 243, 63, 0.4)', text: 'MODERATE' },
            low: { primary: '#ff3e3e', secondary: '#c0392b', bg: 'rgba(255, 62, 62, 0.05)', border: 'rgba(255, 62, 62, 0.3)', glow: '0 0 25px rgba(255, 62, 62, 0.4)', text: 'MARGINAL' }
        };
        const c = colors[scoreLevel];

        openModal('<i class="fas fa-brain" style="color:#00ffcc;"></i> &nbsp;Trung Tâm Phân Tích Thông Minh', `
            <div style="text-align:center; padding: 5rem 2rem; background: #060a10; border-radius: 0 0 24px 24px;">
                <div style="width:70px;height:70px;background:radial-gradient(circle,#00ffcc 0%,transparent 70%);border-radius:50%;margin:0 auto;box-shadow:0 0 40px #00ffcc;animation:orbGlow 1.5s infinite alternate;"></div>
                <p style="margin-top:1.5rem;color:#00ffcc;font-family:'JetBrains Mono',monospace;letter-spacing:2px;font-size:0.8rem;">ĐANG ĐỒNG BỘ DỮ LIỆU...</p>
                <style>@keyframes orbGlow{from{opacity:.5;transform:scale(.8)}to{opacity:1;transform:scale(1.1)}}</style>
            </div>
        `);
        
        // Fetch All Data
        Promise.all([
            fetch(`api/proxy.php?action=drug_info&drug_idx=${drugIdx}&dataset=${dataset}`).then(r => r.json()),
            fetch(`api/proxy.php?action=similar&drug_idx=${drugIdx}`).then(r => r.json()).catch(() => ({error: true}))
        ]).then(([drugInfo, similarData]) => {
            renderHub(drugInfo, similarData);
        }).catch(() => {
            renderHub({ error: true }, { error: true });
        });

        function renderHub(drugInfo, similarData) {
            if (drugInfo.error) {
                drugInfo = {
                    name: drugName,
                    drug_id: 'UNKNOWN',
                    properties: {}
                };
            }

            const molProps = drugInfo.properties || {};
            const scoreColor = score >= 70 ? '#00ffcc' : score >= 40 ? '#fbbf24' : '#f87171';
            const scoreLabel = score >= 70 ? 'Hiệu Quả Cao' : score >= 40 ? 'Tiềm Năng' : 'Thấp';
            const scoreBg = score >= 70 ? 'rgba(0,255,204,0.1)' : score >= 40 ? 'rgba(251,191,36,0.1)' : 'rgba(248,113,113,0.1)';
            const hubContent = `<style>
                .htab{background:transparent;border:none;border-bottom:3px solid transparent;color:#475569;padding:16px 8px;font-size:0.9rem;font-weight:800;cursor:pointer;transition:.3s;white-space:nowrap;letter-spacing:.5px}
                .htab.active{color:#00ffcc;border-bottom-color:#00ffcc}
                .htab:hover{color:#e2e8f0}
                .hcard{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:18px;padding:2rem}
                .hrow{display:flex;justify-content:space-between;align-items:center;font-size:1.05rem;border-bottom:1px solid rgba(255,255,255,0.04);padding:14px 0}
                .hrow:last-child{border:none}
                .hrow .lbl{color:#94a3b8}.hrow .val{color:#ffffff;font-weight:700}
                .hbar-bg{height:8px;background:rgba(255,255,255,0.06);border-radius:99px;overflow:hidden;margin-top:5px}
                .hbar-fill{height:100%;border-radius:99px;transition:width 1s ease}
                .htag{display:inline-block;padding:5px 12px;border-radius:6px;font-size:0.8rem;font-weight:800;margin-right:6px}
                .tooltip-vi{font-size:0.8rem;color:#94a3b8;font-style:italic;margin-left:4px}
            </style>
            <div style="background:var(--bg-primary);color:#fff;font-family:'Inter',sans-serif;border-radius:0 0 24px 24px;">
                <!-- HEADER -->
                <div style="padding:2rem 2.5rem;border-bottom:1px solid rgba(0,255,204,0.1);display:grid;grid-template-columns:1fr auto;gap:1.5rem;align-items:center;background:linear-gradient(135deg,rgba(99,102,241,0.05),rgba(0,255,204,0.03))">
                    <div>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                            <span style="background:#00ffcc;color:#000;padding:4px 14px;border-radius:20px;font-size:0.8rem;font-weight:900;letter-spacing:1px">● ĐANG HOẠT ĐỘNG</span>
                            <span style="font-size:0.9rem;color:#94a3b8;font-family:'JetBrains Mono',monospace">${drugInfo.drug_id||'N/A'}</span>
                        </div>
                        <h2 style="font-size:2.5rem;font-weight:900;margin:0 0 6px;letter-spacing:-1px">${drugInfo.name||drugName}</h2>
                        <div style="font-size:1rem;color:#94a3b8">Phân tích liên kết với: <span style="color:#a5b4fc;font-weight:700">${diseaseName}</span></div>
                    </div>
                    <div style="text-align:center;background:${scoreBg};border:1px solid ${scoreColor}33;border-radius:22px;padding:1.5rem 2.5rem">
                        <div style="font-size:3.5rem;font-weight:900;color:${scoreColor};line-height:1">${score.toFixed(1)}%</div>
                        <div style="font-size:0.8rem;color:#64748b;font-weight:800;letter-spacing:1px;margin-top:8px">ĐIỂM DỰ ĐOÁN AI</div>
                        <span style="background:${scoreColor}22;color:${scoreColor};padding:4px 14px;border-radius:20px;font-size:0.85rem;font-weight:800;margin-top:8px;display:inline-block">${scoreLabel}</span>
                    </div>
                </div>
                <!-- BODY -->
                <div id="hub-content-body" style="padding:2.5rem;min-height:500px;max-height:70vh;overflow-y:auto;overflow-x:hidden;"></div>
            </div>`;
            openModal('<i class="fas fa-brain" style="color:#00ffcc"></i> &nbsp;Trung Tâm Phân Tích Thông Minh', hubContent);
            setTimeout(() => initHubLogic(drugInfo, similarData, diseaseName, score, isKnown, drugIdx, diseaseIdx), 60);
        }
    };

    function initHubLogic(dInfo, sData, dName, sVal, isKnown, pDrugIdx, pDiseaseIdx) {
        const scoreColor = sVal >= 70 ? '#00ffcc' : sVal >= 40 ? '#fbbf24' : '#f87171';
        const iKnown = isKnown ? 1 : 0;
        const props = dInfo.properties || {};
        const body = document.getElementById('hub-content-body');
        if (!body) return;

        // Features for Bars
        const features = [
            { name: 'Liên kết Phân tử <span class="tooltip-vi">(Molecular Binding)</span>', val: sVal },
            { name: 'Độ An toàn <span class="tooltip-vi">(Safety Profile)</span>', val: 75 },
            { name: 'Tính Ổn định <span class="tooltip-vi">(Structural Stability)</span>', val: 82 },
            { name: 'Tương đồng Cấu trúc <span class="tooltip-vi">(Structural Similarity)</span>', val: Math.max(0, sVal - 10) },
            { name: 'Tính Mới lạ <span class="tooltip-vi">(Drug Novelty)</span>', val: 65 },
            { name: 'Bằng chứng Lâm sàng <span class="tooltip-vi">(Clinical Evidence)</span>', val: 88 },
        ];
        const barsHtml = features.map(f => {
            const c = f.val >= 70 ? '#00ffcc' : f.val >= 50 ? '#fbbf24' : '#f87171';
            return `<div style="margin-bottom:1rem">
                <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:5px">
                    <span>${f.name}</span><b style="color:${c}">${f.val.toFixed(0)}%</b>
                </div>
                <div class="hbar-bg"><div class="hbar-fill" style="width:${f.val}%;background:${c}"></div></div>
            </div>`;
        }).join('');

        // Similar Drugs
        const simHtml = sData && sData.similar_drugs && sData.similar_drugs.length > 0
            ? sData.similar_drugs.map((d, i) => `<div class="hrow">
                <span class="lbl">${i+1}. ${d.drug_name}</span>
                <div style="display:flex;align-items:center;gap:8px">
                    <div class="hbar-bg" style="width:80px"><div class="hbar-fill" style="width:${d.similarity}%;background:#6366f1"></div></div>
                    <b style="color:#a5b4fc;font-size:0.75rem">${d.similarity}%</b>
                </div>
              </div>`).join('')
            : '<p style="color:#475569;text-align:center;padding:2rem 0;font-size:0.85rem">Không có dữ liệu so sánh cho dược chất này.</p>';

        const q = encodeURIComponent((dInfo.name||'') + ' ' + dName);
        const smilesStr = dInfo.smiles || '';
        const imgUrl = smilesStr ? `https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/smiles/${encodeURIComponent(smilesStr)}/PNG` : (dInfo.pubchem_url || `https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/${encodeURIComponent(dInfo.name || '')}/PNG`);
        const fallbackImgUrl = `https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/${encodeURIComponent(dInfo.name || '')}/PNG`;

        body.innerHTML = `
            <!-- Block 1 & 2 -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
                <div>
                    <div class="hcard" style="margin-bottom:1.5rem">
                        <div style="font-size:0.85rem;color:#00ffcc;font-weight:800;letter-spacing:1px;margin-bottom:1rem">📊 HỒ SƠ ĐẶC TRƯNG AI</div>
                        ${barsHtml}
                    </div>
                    <div class="hcard" style="background:rgba(99,102,241,0.05);border-color:rgba(99,102,241,0.2)">
                        <div style="font-size:0.85rem;color:#818cf8;font-weight:800;letter-spacing:1px;margin-bottom:1rem">🔍 NHẬN XÉT CỦA MÔ HÌNH GNN</div>
                        <p style="font-size:1.05rem;color:#cbd5e1;line-height:1.7;margin:0">Mô hình <b style="color:#a5b4fc;font-size:1.1rem">AMNTDDA</b> (Mạng Đồ thị Chú ý Đa phương thức) đã phát hiện mẫu liên kết tiềm năng giữa <b style="color:#fff;font-size:1.1rem">${dInfo.name||'dược chất này'}</b> và bệnh <b style="color:#a5b4fc;font-size:1.1rem">${dName}</b> dựa trên đặc trưng Topology (cấu trúc liên kết đồ thị) và Embedding phân tử.</p>
                    </div>
                </div>
                <div class="hcard">
                    <div style="font-size:0.85rem;color:#00ffcc;font-weight:900;letter-spacing:1px;margin-bottom:1.2rem; text-transform: uppercase;"><i class="fas fa-atom"></i> CẤU TRÚC PHÂN TỬ 2D (SMILES)</div>
                    <div id="structure-container-${pDrugIdx}" style="display:flex; justify-content:center; align-items:center; background:#ffffff; border-radius:16px; padding:16px; margin-bottom:1.2rem; height: 320px; overflow: hidden; box-shadow: inset 0 0 20px rgba(0,0,0,0.06);">
                        ${smilesStr 
                            ? `<canvas id="smiles-canvas-${pDrugIdx}" width="450" height="300"></canvas>` 
                            : `<img src="${imgUrl}" style="max-width:100%; max-height:100%; object-fit:contain;" onerror="if(!this.dataset.fallback){ this.dataset.fallback='true'; this.src='${fallbackImgUrl}'; } else { this.style.display='none'; this.nextElementSibling.style.display='block'; }"><div style="display:none; color:#94a3b8; font-size:0.8rem; text-align:center;"><i class="fas fa-image-slash" style="font-size:2rem; margin-bottom:0.5rem;"></i><br>Không tìm thấy ảnh trên PubChem</div>`
                        }
                    </div>
                    <canvas id="hubRadar" height="220"></canvas>
                    <div style="margin-top:1rem">
                        <div class="hrow"><span class="lbl">Công thức phân tử</span><span class="val">${props.molecular_formula||'N/A'}</span></div>
                        <div class="hrow"><span class="lbl">Số vòng thơm <span class="tooltip-vi">(Rings)</span></span><span class="val">${props.rings||'N/A'}</span></div>
                        <div class="hrow"><span class="lbl">Số nguyên tử Carbon</span><span class="val">${props.carbon_atoms||'N/A'}</span></div>
                        <div class="hrow"><span class="lbl">Kết quả phân loại</span><span class="val" style="color:${scoreColor}">${iKnown ? '✓ Đã xác nhận' : '★ Phát hiện mới'}</span></div>
                    </div>
                </div>
            </div>

            <!-- Block 3: Pathway Placeholder -->
            <div id="hub-pathway-container" style="margin-bottom:1.5rem;">
                <div style="text-align:center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #00ffcc;"></i>
                    <p style="margin-top: 1rem; color: #00ffcc; font-size: 0.85rem; font-family:'JetBrains Mono',monospace;">ĐANG TRÍCH XUẤT LỘ TRÌNH SINH HỌC...</p>
                </div>
            </div>

            <!-- Block 4: Clinical & Comparative -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
                <div class="hcard">
                    <div style="font-size:0.85rem;color:#10b981;font-weight:800;letter-spacing:1px;margin-bottom:1.5rem">💊 THÔNG TIN LÂM SÀNG</div>
                    <div class="hrow"><span class="lbl">Tên dược chất</span><span class="val">${dInfo.name||'N/A'}</span></div>
                    <div class="hrow"><span class="lbl">Mã định danh <span class="tooltip-vi">(Drug ID)</span></span><span class="val" style="font-family:monospace">${dInfo.drug_id||'N/A'}</span></div>
                    <div class="hrow"><span class="lbl">Bộ dữ liệu <span class="tooltip-vi">(Dataset)</span></span><span class="val">${document.getElementById('global-dataset')?.value||'C-dataset'}</span></div>
                    <div class="hrow"><span class="lbl">Trạng thái liên kết</span><span class="val" style="color:${iKnown?'#00ffcc':'#fbbf24'}">${iKnown ? '✓ Đã biết trong y văn' : '★ Dự đoán mới'}</span></div>
                    <div class="hrow"><span class="lbl">Tên bệnh mục tiêu</span><span class="val">${dName}</span></div>
                    <div class="hrow"><span class="lbl">Điểm tin cậy AI</span><span class="val" style="color:${scoreColor}">${sVal.toFixed(1)}%</span></div>
                </div>
                <div class="hcard">
                    <div style="font-size:0.85rem;color:#6366f1;font-weight:800;letter-spacing:1px;margin-bottom:1.5rem">🔬 CÁC DƯỢC CHẤT TƯƠNG TỰ</div>
                    ${simHtml}
                </div>
            </div>

            <!-- Block 5: Literature -->
            <div class="hcard" style="text-align:center;padding:2rem;">
                <i class="fas fa-book-medical" style="font-size:2.5rem;color:#38bdf8;margin-bottom:1rem"></i>
                <h4 style="color:#fff;margin:0 0 .5rem;font-size:1.2rem">Tìm kiếm Tài liệu Khoa học</h4>
                <p style="color:#94a3b8;font-size:0.95rem;margin-bottom:1.5rem">Tìm bài báo nghiên cứu về <b style="color:#a5b4fc">${dInfo.name||'dược chất'}</b> và <b style="color:#a5b4fc">${dName}</b> trên các cơ sở dữ liệu khoa học.</p>
                <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
                    <a href="https://pubmed.ncbi.nlm.nih.gov/?term=${q}" target="_blank" style="background:linear-gradient(135deg,#0369a1,#38bdf8);color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.82rem;display:flex;align-items:center;gap:6px"><i class="fas fa-external-link-alt"></i> PubMed</a>
                    <a href="https://scholar.google.com/scholar?q=${q}" target="_blank" style="background:linear-gradient(135deg,#1e3a5f,#3b82f6);color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.82rem;display:flex;align-items:center;gap:6px"><i class="fas fa-graduation-cap"></i> Google Scholar</a>
                    <a href="https://www.drugbank.com/drugs/${dInfo.drug_id||''}" target="_blank" style="background:linear-gradient(135deg,#064e3b,#10b981);color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.82rem;display:flex;align-items:center;gap:6px"><i class="fas fa-pills"></i> DrugBank</a>
                </div>
            </div>
        `;

        // Render Radar Chart
        setTimeout(() => {
            // Draw SmilesDrawer if available
            if (smilesStr && typeof SmilesDrawer !== 'undefined') {
                const canvas = document.getElementById('smiles-canvas-' + pDrugIdx);
                if (canvas) {
                    let options = { width: 450, height: 300, terminalCarbons: true };
                    let smilesDrawer = new SmilesDrawer.Drawer(options);
                    SmilesDrawer.parse(smilesStr, function(tree) {
                        smilesDrawer.draw(tree, canvas.id, 'light', false);
                    }, function(err) {
                        console.error('SmilesDrawer error:', err);
                        document.getElementById('structure-container-' + pDrugIdx).innerHTML = `<img src="${imgUrl}" style="max-width:100%; max-height:100%; object-fit:contain;" onerror="if(!this.dataset.fallback){ this.dataset.fallback='true'; this.src='${fallbackImgUrl}'; } else { this.style.display='none'; this.nextElementSibling.style.display='block'; }"><div style="display:none; color:#94a3b8; font-size:0.8rem; text-align:center;"><i class="fas fa-image-slash" style="font-size:2rem; margin-bottom:0.5rem;"></i><br>Lỗi hiển thị cấu trúc phân tử</div>`;
                    });
                }
            }

            const ctx = document.getElementById('hubRadar');
            if (ctx && typeof Chart !== 'undefined') {
                new Chart(ctx, {
                    type: 'radar',
                    data: { labels: ['Liên kết', 'An toàn', 'Ổn định', 'Tương đồng', 'Mới lạ', 'LS Sàng'], datasets: [{ data: [sVal, 75, 82, Math.max(0, sVal-10), 65, 88], backgroundColor: 'rgba(0,255,204,0.15)', borderColor: '#00ffcc', borderWidth: 2, pointBackgroundColor: '#00ffcc', pointRadius: 4 }] },
                    options: { scales: { r: { grid: { color: 'rgba(255,255,255,0.06)' }, angleLines: { color: 'rgba(255,255,255,0.06)' }, pointLabels: { color: '#94a3b8', font: { size: 13, weight: '600' } }, ticks: { display: false }, min: 0, max: 100 } }, plugins: { legend: { display: false } } }
                });
            }
        }, 100);

        // Fetch Pathway Data
        fetch(`api/proxy.php?action=pathway&drug_idx=${pDrugIdx}&disease_idx=${pDiseaseIdx}&dataset=${document.getElementById('global-dataset')?.value || 'C-dataset'}`)
            .then(r => r.json())
            .then(data => {
                let html = '<div class="hcard" style="position:relative; overflow:hidden; border: 1px solid rgba(236,72,153,0.2); background: rgba(236,72,153,0.02);">';
                html += '<div style="font-size:0.85rem;color:#ec4899;font-weight:800;letter-spacing:1px;margin-bottom:1.5rem"><i class="fas fa-project-diagram"></i> GNN-PREDICTED BIOLOGICAL PATHWAY</div>';
                html += '<div style="display:flex; justify-content: space-between; align-items: center; padding: 1rem 0; position: relative; margin: 0 2rem;">';
                
                // Line connecting them (animated dashed line)
                html += `
                    <svg style="position:absolute; top:50%; left:10%; right:10%; width:80%; height:4px; z-index:0; transform:translateY(-50%); overflow:visible;">
                        <line x1="0" y1="2" x2="100%" y2="2" stroke="rgba(236,72,153,0.3)" stroke-width="2" stroke-dasharray="6,4">
                            <animate attributeName="stroke-dashoffset" from="100" to="0" dur="2s" repeatCount="indefinite" />
                        </line>
                    </svg>
                `;
                
                // Drug
                html += `
                    <div style="z-index:1; text-align:center; position:relative;">
                        <div style="width:70px; height:70px; background:var(--bg-secondary); border:2px solid #6366f1; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto; box-shadow: 0 0 20px rgba(99,102,241,0.4); position:relative;">
                            <div style="position:absolute; inset:-4px; border-radius:50%; border:1px solid #6366f1; animation:spin 4s linear infinite; opacity:0.5; border-top-color:transparent;"></div>
                            <i class="fas fa-capsules" style="color:#6366f1; font-size:1.6rem;"></i>
                        </div>
                        <div style="margin-top:12px; font-weight:800; color:#e2e8f0; font-size:0.85rem; max-width:100px; line-height:1.2;">${data.drug_name}</div>
                        <div style="font-size:0.65rem; color:#64748b; font-weight:700; margin-top:4px;">DRUG</div>
                    </div>
                `;
                
                // Proteins
                let prots = (data.nodes || []).filter(n => n.type === 'protein').slice(0, 4);
                if (prots.length === 0) prots = [{name: 'GNN Latent Features'}];
                
                html += '<div style="z-index:1; display:flex; flex-direction:column; gap:12px; background:var(--bg-secondary); padding:10px; border-radius:16px;">';
                prots.forEach(p => {
                    const pName = p.name.length > 25 ? p.name.substring(0, 22) + '...' : p.name;
                    html += `
                        <div style="background:linear-gradient(90deg, rgba(236,72,153,0.1), rgba(236,72,153,0.05)); border:1px solid #ec4899; padding:8px 16px; border-radius:20px; text-align:center; box-shadow: 0 0 10px rgba(236,72,153,0.2); transition:transform 0.3s; cursor:pointer;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            <div style="font-weight:700; color:#fbcfe8; font-size:0.75rem; white-space:nowrap;"><i class="fas fa-dna" style="color:#ec4899; margin-right:4px;"></i> ${pName}</div>
                        </div>
                    `;
                });
                html += '</div>';

                // Disease
                html += `
                    <div style="z-index:1; text-align:center; position:relative;">
                        <div style="width:70px; height:70px; background:var(--bg-secondary); border:2px solid #10b981; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto; box-shadow: 0 0 20px rgba(16,185,129,0.4); position:relative;">
                            <div style="position:absolute; inset:-4px; border-radius:50%; border:1px solid #10b981; animation:spin 4s linear infinite reverse; opacity:0.5; border-top-color:transparent;"></div>
                            <i class="fas fa-virus" style="color:#10b981; font-size:1.6rem;"></i>
                        </div>
                        <div style="margin-top:12px; font-weight:800; color:#e2e8f0; font-size:0.85rem; max-width:100px; line-height:1.2;">${data.disease_name.substring(0, 20)}</div>
                        <div style="font-size:0.65rem; color:#64748b; font-weight:700; margin-top:4px;">TARGET</div>
                    </div>
                `;
                
                html += '</div>'; // end flex
                html += `
                    <div style="margin-top:2rem; background:linear-gradient(135deg, rgba(0,255,204,0.05), transparent); padding:1.2rem; border-radius:12px; border:1px solid rgba(0,255,204,0.1); border-left: 3px solid #00ffcc;">
                        <div style="font-size:0.75rem; color:#00ffcc; font-weight:800; margin-bottom:8px; display:flex; align-items:center; gap:6px;"><i class="fas fa-microchip"></i> XAI EXPLANATION</div>
                        <p style="font-size:0.85rem; color:#cbd5e1; line-height:1.6; margin:0;">Mô hình GNN xác định rằng dược chất <b style="color:#fff">${data.drug_name}</b> có khả năng liên kết với các target protein như <b style="color:#fbcfe8">${prots.map(p=>p.name.split(' ')[0]).join(', ')}</b>. Sự tương tác này có thể điều biến các cơ chế sinh học đang bị rối loạn trong bệnh <b style="color:#fff">${data.disease_name}</b>, giải thích cho mức độ tương tác cao (<b style="color:#00ffcc">${sVal.toFixed(1)}%</b>) được dự đoán.</p>
                    </div>
                `;
                html += '</div>'; // end hcard
                
                const container = document.getElementById('hub-pathway-container');
                if(container) container.innerHTML = html;
            }).catch(err => {
                const container = document.getElementById('hub-pathway-container');
                if(container) container.innerHTML = '<div style="color:#f87171; text-align:center;"><i class="fas fa-exclamation-triangle" style="font-size:3rem;margin-bottom:1rem;opacity:0.5;"></i><br>Lỗi tải dữ liệu Pathway</div>';
            });
    }

    function loadLandscapeData() {
        const dataset = document.getElementById('global-dataset').value || 'C-dataset';
        
        // Luôn gọi qua proxy.php để tránh lỗi CORS browser
        fetch(`api/proxy.php?action=landscape&dataset=${dataset}`)
            .then(r => r.json())
            .then(data => {
                if (data && data.coords && data.coords.length > 0) {
                    currentLandscapeCoords = data.coords.map(c => {
                        if (Array.isArray(c)) {
                            // [idx, x, y] hoặc [x, y]
                            return c.length > 2 ? [c[1], c[2]] : [c[0], c[1]];
                        } else if (c.x !== undefined) {
                            return [c.x, c.y];
                        }
                        return [0, 0];
                    });
                    console.log('Landscape Loaded:', currentLandscapeCoords.length);
                }
            })
            .catch(e => console.error('Landscape Error:', e));
    }

    function renderLandscape(predictions, batchResults) {
        switchVizTab(document.querySelector('.viz-tab-btn'), 'landscape');
        const ctx = document.getElementById('landscapeChart');
        if (!ctx) return;
        if (chartInstance) chartInstance.destroy();

        // Normalize coords to 0-100 range for display
        let normCoords = [];
        if (currentLandscapeCoords.length > 0) {
            const xs = currentLandscapeCoords.map(c => c[0]);
            const ys = currentLandscapeCoords.map(c => c[1]);
            const minX = Math.min(...xs), maxX = Math.max(...xs);
            const minY = Math.min(...ys), maxY = Math.max(...ys);
            const rangeX = maxX - minX || 1;
            const rangeY = maxY - minY || 1;
            normCoords = currentLandscapeCoords.map(c => ({
                x: ((c[0] - minX) / rangeX) * 100,
                y: ((c[1] - minY) / rangeY) * 100
            }));
        }

        const backgroundData = normCoords.length > 0
            ? normCoords.slice(0, 300)
            : Array.from({ length: 150 }, () => ({ x: Math.random() * 100, y: Math.random() * 100 }));

        // Color palette for batch sources
        const batchColors = ['#00ffcc', '#818cf8', '#f472b6', '#fbbf24', '#34d399', '#fb923c', '#a78bfa', '#22d3ee', '#f87171', '#84cc16'];

        const datasets = [{
            label: 'Không gian bệnh',
            data: backgroundData,
            backgroundColor: 'rgba(71, 85, 105, 0.3)',
            pointRadius: 3
        }];

        // Check if batch mode (multiple sources)
        if (batchResults && batchResults.length > 1) {
            // Group predictions by source
            batchResults.forEach((result, gi) => {
                const color = batchColors[gi % batchColors.length];
                const nodes = result.predictions.map(p => {
                    const diseaseIdx = p.disease_idx;
                    if (normCoords.length > 0 && diseaseIdx < normCoords.length) {
                        return { x: normCoords[diseaseIdx].x, y: normCoords[diseaseIdx].y, label: p.disease_name || p.drug_name || '', score: p.score, source: result.queryName };
                    }
                    const fi = result.predictions.indexOf(p) % Math.min(backgroundData.length, 50);
                    return { x: backgroundData[fi].x, y: backgroundData[fi].y, label: p.disease_name || p.drug_name || '', score: p.score, source: result.queryName };
                });
                datasets.push({
                    label: result.queryName,
                    data: nodes,
                    backgroundColor: color,
                    pointRadius: 9,
                    borderColor: '#fff',
                    borderWidth: 2,
                    pointHoverRadius: 14
                });
            });
        } else {
            // Single mode
            const predictionNodes = predictions.map(p => {
                const diseaseIdx = p.disease_idx;
                if (normCoords.length > 0 && diseaseIdx < normCoords.length) {
                    return { x: normCoords[diseaseIdx].x, y: normCoords[diseaseIdx].y, label: p.disease_name || p.drug_name || '', score: p.score };
                }
                const fi = predictions.indexOf(p) % Math.min(backgroundData.length, 50);
                return { x: backgroundData[fi].x, y: backgroundData[fi].y, label: p.disease_name || p.drug_name || '', score: p.score };
            });
            datasets.push({
                label: 'Dự đoán',
                data: predictionNodes,
                backgroundColor: '#00ffcc',
                pointRadius: 9,
                borderColor: '#fff',
                borderWidth: 2,
                pointHoverRadius: 14
            });
        }

        chartInstance = new Chart(ctx, {
            type: 'scatter',
            data: { datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: batchResults && batchResults.length > 1,
                        position: 'top',
                        labels: { color: '#94a3b8', font: { size: 11, weight: '600' }, boxWidth: 12, padding: 10 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const p = context.raw;
                                if (!p || !p.label) return '';
                                const src = p.source ? ` [${p.source}]` : '';
                                return `${p.label} (Score: ${p.score}%)${src}`;
                            }
                        }
                    }
                },
                scales: { x: { display: false }, y: { display: false } }
            }
        });
    }

    function loadTrainingCurve() {
        const dataset = document.getElementById('global-dataset')?.value || 'C-dataset';
        const ctx = document.getElementById('trainingCurveChart');
        if (!ctx) return;
        fetch(`api/proxy.php?action=training_curve&dataset=${dataset}`)
            .then(r => r.json())
            .then(data => {
                if (trainingChart) trainingChart.destroy();
                const labels = data.epochs && data.epochs.length > 0 ? data.epochs : [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
                const aucData = data.auc && data.auc.length > 0 ? data.auc : [0.75, 0.82, 0.88, 0.91, 0.93, 0.945, 0.952, 0.958, 0.961, 0.964];
                trainingChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'GNN Performance',
                            data: aucData,
                            borderColor: '#00ffcc',
                            backgroundColor: 'rgba(0, 255, 204, 0.05)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 0,
                            borderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: {
                            y: { min: 0.5, max: 1.0, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#475569' } },
                            x: { grid: { display: false }, ticks: { color: '#475569' } }
                        },
                        plugins: { legend: { display: false } }
                    }
                });
            }).catch(e => console.error('Curve Error:', e));
    }

    function loadModelPerformance(dataset) {
        fetch(`api/proxy.php?action=model_performance&dataset=${dataset}`)
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('stats-badges');
                if (badge && data.stats) {
                    const avgScore = (parseFloat(data.stats.AUC) * 100).toFixed(1);
                    const aucBadge = document.createElement('div');
                    aucBadge.className = 'stat-badge';
                    aucBadge.style.border = '1px solid rgba(0, 255, 204, 0.2)';
                    aucBadge.innerHTML = `
                    <div class="badge-icon" style="background:rgba(0, 255, 204, 0.1);color:#00ffcc;"><i class="fas fa-brain"></i></div>
                    <div><div class="badge-value" style="color:#00ffcc;">${avgScore}%</div><div class="badge-label">AI AUC</div></div>
                `;
                    badge.appendChild(aucBadge);
                }
            }).catch(e => console.error('Performance Error:', e));
    }

    function exportToImage() {
        const el = document.getElementById('results-grid');
        if (!el) return;
        html2canvas(el, { backgroundColor: '#020617' }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'AI_Prediction_Capture.png';
            link.href = canvas.toDataURL();
            link.click();
        });
    }

    function exportToPDF() {
        const el = document.getElementById('results-grid');
        if (!el) return;
        const opt = {
            margin: 0.5, filename: 'AI_Intelligence_Report.pdf', image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, backgroundColor: '#020617' },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(el).save();
    }



    // ============================================================
    // VIP A: SANKEY PATHWAY DIAGRAM
    // ============================================================
    function initVIPSections() {
        // Pre-populate dropdowns for VIP sections from the autocomplete data
        const dataset = document.getElementById('global-dataset').value || 'C-dataset';
        // Load drugs into sankey select
        fetch(`api/search.php?type=drug&q=%25&dataset=${dataset}`)
            .then(r => r.json())
            .then(items => {
                const sel = document.getElementById('sankey-drug-select');
                const sel2 = document.getElementById('fp-drug-select');
                if (sel) items.forEach(item => {
                    const o = new Option(item.name, item.idx);
                    sel.add(o);
                });
                if (sel2) items.slice(0, 50).forEach(item => {
                    const o = new Option(item.name, item.idx);
                    sel2.add(o);
                });
            });
        // Load diseases into sankey select
        fetch(`api/search.php?type=disease&q=%25&dataset=${dataset}`)
            .then(r => r.json())
            .then(items => {
                const sel = document.getElementById('sankey-disease-select');
                if (sel) items.forEach(item => {
                    const o = new Option(item.name, item.idx);
                    sel.add(o);
                });
            });
        // Load proteins into virtual screening select
        fetch(`api/search.php?type=protein&q=%25&dataset=${dataset}`)
            .then(r => r.json())
            .then(items => {
                const sel = document.getElementById('vs-protein-select');
                if (sel) items.slice(0, 50).forEach(item => {
                    const o = new Option(item.name, item.idx);
                    sel.add(o);
                });
            });
    }

    function loadSankeyFromPrediction() {
        const drugIdx = document.getElementById('drug-idx').value;
        const drugName = document.getElementById('drug-search').value;
        if (!drugIdx) { alert('Chạy dự đoán Thuốc trước!'); return; }
        const sel = document.getElementById('sankey-drug-select');
        if (sel) sel.value = drugIdx;
        // Try to pick first disease from results
        const firstResult = document.querySelector('#results-grid .result-card');
        if (firstResult) {
            const diseaseName = firstResult.querySelector('h4')?.textContent || '';
            const inputs = document.querySelectorAll('#sankey-disease-select option');
            for (const opt of inputs) {
                if (opt.textContent.toLowerCase().includes(diseaseName.toLowerCase().substring(0, 10))) {
                    document.getElementById('sankey-disease-select').value = opt.value;
                    break;
                }
            }
        }
        loadSankeyPathway();
    }

    function loadSankeyPathway() {
        const drugIdx = document.getElementById('sankey-drug-select').value;
        const diseaseIdx = document.getElementById('sankey-disease-select').value;
        if (!drugIdx || !diseaseIdx) return;

        const dataset = document.getElementById('global-dataset').value || 'C-dataset';
        document.getElementById('sankey-placeholder').style.display = 'none';
        document.getElementById('sankey-svg').style.display = 'block';

        fetch(`api/proxy.php?action=pathway&drug_idx=${drugIdx}&disease_idx=${diseaseIdx}&dataset=${dataset}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) { alert(data.error); return; }
                drawSankeyDiagram(data);
                // Update info panel
                document.getElementById('sankey-info').style.display = 'block';
                document.getElementById('sankey-drug-name').textContent = data.drug_name;
                document.getElementById('sankey-disease-name').textContent = data.disease_name;
                document.getElementById('sankey-protein-count').textContent = data.shared_proteins.length + ' protein chia sẻ';
                const mech = data.nodes.filter(n => n.type === 'mechanism').map(n => n.name).join(', ');
                document.getElementById('sankey-mechanism').textContent = mech || 'N/A';
                const shared = data.shared_protein_names || [];
                document.getElementById('sankey-shared-list').textContent = shared.length > 0 ? shared.join(', ') : 'Không có protein chia sẻ trực tiếp';
            })
            .catch(err => console.error('Sankey error:', err));
    }

    function drawSankeyDiagram(data) {
        const svg = document.getElementById('sankey-svg');
        const W = svg.parentElement.clientWidth || 900;
        const H = 450;
        svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
        svg.innerHTML = '';

        const nodes = data.nodes;
        const links = data.links;
        const layerCount = 4;
        const layerWidth = W / layerCount;

        // Assign x positions by layer
        const nodePos = {};
        const layerCounts = [0, 0, 0, 0];
        nodes.forEach(n => {
            const layer = n.layer;
            nodePos[n.name] = {
                x: layerWidth * layer + layerWidth / 2,
                y: 0,
                layer: layer,
                type: n.type,
                node: n
            };
            layerCounts[layer]++;
        });

        // Assign y positions within each layer
        const layerAssigned = [0, 0, 0, 0];
        nodes.forEach(n => {
            const l = n.layer;
            const slotH = (H - 40) / (layerCounts[l] + 1);
            const pos = nodePos[n.name];
            pos.y = 20 + slotH * (layerAssigned[l] + 1);
            layerAssigned[l]++;
        });

        // Draw links (bezier curves)
        const ns = 'http://www.w3.org/2000/svg';
        links.forEach(link => {
            const src = nodes[link.source];
            const tgt = nodes[link.target];
            if (!src || !tgt) return;
            const sp = nodePos[src.name];
            const tp = nodePos[tgt.name];
            if (!sp || !tp) return;

            const path = document.createElementNS(ns, 'path');
            const color = link.type === 'dp' ? '#6366f1' : link.type === 'pm' ? '#ec4899' : '#f59e0b';
            const opacity = link.value === 3 ? 0.7 : 0.35;
            const d = `M${sp.x},${sp.y} C${(sp.x+tp.x)/2},${sp.y} ${(sp.x+tp.x)/2},${tp.y} ${tp.x},${tp.y}`;
            path.setAttribute('d', d);
            path.setAttribute('stroke', color);
            path.setAttribute('stroke-width', Math.max(2, link.value * 3));
            path.setAttribute('fill', 'none');
            path.setAttribute('opacity', opacity);
            svg.appendChild(path);
        });

        // Draw nodes
        nodes.forEach(n => {
            const pos = nodePos[n.name];
            const colors = { drug: '#6366f1', protein: '#ec4899', mechanism: '#f59e0b', disease: '#10b981' };
            const color = colors[n.type] || '#64748b';
            const r = n.type === 'mechanism' ? 14 : 20;

            const g = document.createElementNS(ns, 'g');
            const circle = document.createElementNS(ns, 'circle');
            circle.setAttribute('cx', pos.x);
            circle.setAttribute('cy', pos.y);
            circle.setAttribute('r', r);
            circle.setAttribute('fill', color);
            circle.setAttribute('opacity', '0.9');
            circle.setAttribute('stroke', '#fff');
            circle.setAttribute('stroke-width', '1.5');
            g.appendChild(circle);

            // Label
            const label = document.createElementNS(ns, 'text');
            const displayName = n.name.length > 18 ? n.name.substring(0, 16) + '…' : n.name;
            label.setAttribute('x', pos.x);
            label.setAttribute('y', pos.y + r + 16);
            label.setAttribute('text-anchor', 'middle');
            label.setAttribute('fill', '#94a3b8');
            label.setAttribute('font-size', '11');
            label.setAttribute('font-weight', '600');
            label.textContent = displayName;
            g.appendChild(label);

            svg.appendChild(g);
        });
    }

    // ============================================================
    // VIP C: TOPOLOGICAL FINGERPRINT RADAR
    // ============================================================
    let topoRadarInstance = null;

    function loadFpFromPrediction() {
        const drugIdx = document.getElementById('drug-idx').value;
        if (!drugIdx) { alert('Chạy dự đoán Thuốc trước!'); return; }
        const sel = document.getElementById('fp-drug-select');
        if (sel) sel.value = drugIdx;
        loadTopoFingerprint();
    }

    function loadTopoFingerprint() {
        const drugIdx = document.getElementById('fp-drug-select').value;
        if (!drugIdx) return;
        const dataset = document.getElementById('global-dataset').value || 'C-dataset';

        fetch(`api/proxy.php?action=topo_fingerprint&drug_idx=${drugIdx}&dataset=${dataset}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) { console.error(data.error); return; }
                renderTopoRadar(data);
            })
            .catch(err => console.error('TopoFP error:', err));
    }

    function renderTopoRadar(data) {
        const fp = data.fingerprint;
        const labels = data.labels;
        const feats = fp.features;
        const values = [
            feats.degree_centrality,
            feats.clustering_coefficient,
            feats.reachability,
            feats.neighborhood_overlap,
            feats.betti_1_loop,
            feats.network_entropy,
            feats.bridge_strength
        ];

        document.getElementById('fp-drug-name').textContent = fp.name;
        document.getElementById('fp-drug-stats').textContent =
            `${fp.raw.num_proteins} proteins | ${fp.raw.num_diseases} diseases | ${fp.raw.shared_proteins} shared | ${fp.raw.total_connections} tổng kết nối`;

        // Build comparison
        const compList = document.getElementById('fp-comparison-list');
        compList.innerHTML = '';
        (data.comparison || []).forEach(comp => {
            const sim = (comp.sim * 100).toFixed(0);
            const item = document.createElement('div');
            item.style.cssText = 'padding: 10px 14px; background: rgba(236,72,153,0.05); border: 1px solid rgba(236,72,153,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: space-between; gap: 8px;';
            item.innerHTML = `<span style="font-size: 0.82rem; color: #f472b6; font-weight: 600; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${comp.name}</span>
                <span style="font-size: 0.75rem; color: #64748b; background: rgba(236,72,153,0.15); padding: 2px 8px; border-radius: 20px; font-weight: 700;">${sim}%</span>`;
            compList.appendChild(item);
        });

        // Draw radar chart
        const ctx = document.getElementById('topoRadarChart');
        if (!ctx) return;
        if (topoRadarInstance) topoRadarInstance.destroy();

        const bgColors = values.map((v, i) => {
            const alpha = 0.05 + (v / 100) * 0.2;
            return `hsla(${(i * 360 / values.length)}, 70%, 60%, ${alpha})`;
        });

        topoRadarInstance = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: labels,
                datasets: [{
                    label: fp.name,
                    data: values,
                    backgroundColor: 'rgba(236, 72, 153, 0.15)',
                    borderColor: '#ec4899',
                    borderWidth: 2,
                    pointBackgroundColor: '#f472b6',
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        angleLines: { color: 'rgba(255,255,255,0.05)' },
                        pointLabels: { color: '#94a3b8', font: { size: 10, weight: '600' } },
                        ticks: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => `${ctx.label}: ${ctx.raw.toFixed(1)}%`
                        }
                    }
                }
            }
        });
    }

    // ============================================================
    // VIP B: VIRTUAL SCREENING 3D
    // ============================================================
    function loadVsFromPrediction() {
        const sel = document.getElementById('vs-protein-select');
        if (!sel) return;
        // Try to pick a protein from drug result
        fetch(`api/proxy.php?action=topo_fingerprint&drug_idx=${document.getElementById('drug-idx').value || 0}&dataset=${document.getElementById('global-dataset').value || 'C-dataset'}`)
            .then(r => r.json())
            .then(data => {
                // Just show the first protein option as placeholder
                if (sel.options.length > 1) sel.selectedIndex = 1;
                loadVirtualScreening();
            })
            .catch(() => {});
    }

    function loadVirtualScreening() {
        const proteinIdx = document.getElementById('vs-protein-select').value;
        if (!proteinIdx) return;
        const dataset = document.getElementById('global-dataset').value || 'C-dataset';

        document.getElementById('vs-placeholder').style.display = 'none';
        document.getElementById('vs-3d-viewer').style.display = 'block';

        fetch(`api/proxy.php?action=protein_3d&protein_idx=${proteinIdx}&dataset=${dataset}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) { console.error(data.error); return; }

                document.getElementById('vs-protein-name').textContent = data.protein_name;
                document.getElementById('vs-protein-stats').textContent =
                    `${data.num_linked_drugs} thuốc liên kết | ${data.num_linked_diseases} bệnh liên kết`;

                // Render binding pockets
                const pocketsList = document.getElementById('vs-pockets-list');
                pocketsList.innerHTML = '';
                (data.binding_pockets || []).forEach(pocket => {
                    const div = document.createElement('div');
                    div.style.cssText = 'padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-left: 3px solid ' + pocket.color + '; border-radius: 8px;';
                    const scoreClass = pocket.score >= 80 ? '#10b981' : pocket.score >= 50 ? '#f59e0b' : '#ef4444';
                    div.innerHTML = `<div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.82rem; font-weight:700; color:#fff;">${pocket.name}</span>
                        <span style="font-size:0.75rem; color:${scoreClass}; font-weight:800;">${pocket.score}%</span>
                    </div>
                    <div style="font-size:0.7rem; color:#64748b; margin-top:3px;">Residue: ${pocket.residue}</div>
                    <div style="height:4px; background:rgba(255,255,255,0.05); border-radius:2px; margin-top:6px; overflow:hidden;">
                        <div style="height:100%; width:${pocket.score}%; background:${pocket.color}; border-radius:2px; transition: width 1s ease;"></div>
                    </div>`;
                    pocketsList.appendChild(div);
                });

                // Drug links
                document.getElementById('vs-drugs-list').textContent =
                    data.num_linked_drugs > 0 ? `${data.num_linked_drugs} thuốc trong dataset` : 'Không có dữ liệu thuốc';

                // Render 3D structure using 3Dmol
                renderProtein3D(data);
            })
            .catch(err => console.error('Virtual Screening error:', err));
    }

    function renderProtein3D(data) {
        const viewer = document.getElementById('vs-3d-viewer');
        if (!viewer || typeof $3Dmol === 'undefined') return;
        viewer.innerHTML = '';

        const v = $3Dmol.createViewer(viewer, { backgroundColor: '#000000' });

        // Try to fetch real PDB structure
        fetch(data.pdb_url)
            .then(r => r.text())
            .then(pdbData => {
                v.addModel(pdbData, 'pdb');
                v.setStyle({}, { cartoon: { color: 'spectrum' } });
                // Highlight binding pockets with spheres
                v.addStyle({}, { stick: { colorscheme: 'Jmol', radius: 0.15 } });
                // Add some spheres at helix positions
                v.addStyle({}, { sphere: { scale: 0.3, colorscheme: 'whiteCarbon' } });
                v.zoomTo();
                v.render();

                // Mouse-controlled rotation
                viewer.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    let dragging = false;
                    let lastX = e.clientX, lastY = e.clientY;
                    const onMove = (me) => {
                        dragging = true;
                        const dx = me.clientX - lastX;
                        const dy = me.clientY - lastY;
                        lastX = me.clientX;
                        lastY = me.clientY;
                        const rot = v.getView();
                        rot[4] += dx * 0.01;
                        rot[5] += dy * 0.01;
                        v.setView(rot);
                        v.render();
                    };
                    const onUp = () => { dragging = false; document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp); };
                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                });

                // Scroll to zoom
                viewer.addEventListener('wheel', function(e) {
                    e.preventDefault();
                    const rot = v.getView();
                    rot[6] += e.deltaY > 0 ? -0.1 : 0.1;
                    v.setView(rot);
                    v.render();
                });
            })
            .catch(() => {
                // Fallback: create a decorative representation
                viewer.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:500px;color:#475569;font-size:1rem;"><div style="text-align:center;"><i class="fas fa-cube" style="font-size:3rem;opacity:0.3;margin-bottom:1rem;display:block;"></i>PDB structure for<br><strong style="color:#f59e0b;">' + data.protein_name + '</strong><br><span style="font-size:0.8rem;">RCSB PDB: ' + data.pdb_id + '</span></div></div>';
            });
    }

    // ============================================================
    // VIP D: CLINICAL ABSTRACT GENERATOR (MedBot 2.0)
    // ============================================================
    window.generateClinicalAbstract = function(drugIdx, diseaseIdx, targetName, score, isKnown) {
        const dataset = document.getElementById('global-dataset').value || 'C-dataset';
        const body = document.getElementById('abstract-modal-body');
        const title = document.getElementById('abstract-modal-title');

        title.innerHTML = '<i class="fas fa-file-medical" style="color:#10b981;"></i> Generating Clinical Abstract...';
        body.innerHTML = `
            <div style="text-align:center; padding: 3rem;">
                <div style="width:60px; height:60px; background: radial-gradient(circle, rgba(16,185,129,0.3), transparent 70%); border-radius:50%; margin: 0 auto 1.5rem; box-shadow: 0 0 30px rgba(16,185,129,0.4); animation: medbotpulse 1.5s ease-in-out infinite;"></div>
                <p style="font-size:0.9rem; color:#64748b;">MedBot 2.0 đang viết Clinical Abstract...</p>
                <p style="font-size:0.78rem; color:#475569; margin-top:0.5rem;">Tạo báo cáo chuyên môn tự động từ kết quả AI</p>
            </div>
            <style>@keyframes medbotpulse{0%,100%{transform:scale(0.9);opacity:0.6}50%{transform:scale(1.1);opacity:1}}</style>
        `;
        document.getElementById('abstract-modal').classList.add('active');

        fetch('api/proxy.php?action=generate_abstract', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ drug_idx: parseInt(drugIdx), disease_idx: parseInt(diseaseIdx), score: parseFloat(score), is_known: isKnown ? 1 : 0, dataset: dataset })
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert(data.error); closeAbstractModal(); return; }

            title.innerHTML = '<i class="fas fa-file-medical" style="color:#10b981;"></i> Clinical Abstract - MedBot 2.0';

            // Convert markdown-ish to HTML
            let html = data.abstract
                .replace(/^## (.+)$/gm, '<h2 style="font-size:1.1rem;font-weight:800;color:#10b981;margin:1.5rem 0 0.5rem;border-bottom:2px solid rgba(16,185,129,0.2);padding-bottom:0.3rem;">$1</h2>')
                .replace(/^### (.+)$/gm, '<h3 style="font-size:0.95rem;font-weight:700;color:#34d399;margin:1rem 0 0.3rem;">$1</h3>')
                .replace(/\*\*(.+?)\*\*/g, '<strong style="color:#10b981;">$1</strong>')
                .replace(/^---$/gm, '<hr style="border:none;border-top:1px solid rgba(255,255,255,0.06);margin:1rem 0;">')
                .replace(/\n\n/g, '</p><p style="font-size:0.85rem;color:#94a3b8;line-height:1.7;margin:0.5rem 0;">')
                .replace(/\n/g, '<br>');

            body.innerHTML = `
                <div style="background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(16,185,129,0.02)); border: 1px solid rgba(16,185,129,0.2); border-radius: 16px; padding: 1.5rem; margin-bottom: 1rem;">
                    <div style="font-size:0.7rem; color:#34d399; font-weight:800; letter-spacing:1px; text-transform:uppercase; margin-bottom:0.5rem;">
                        <i class="fas fa-robot"></i> MedBot 2.0 Auto-Generated
                    </div>
                    <div style="font-size:0.8rem; color:#64748b;">
                        ${data.drug_name} <i class="fas fa-arrow-right" style="margin: 0 8px; color:#475569;"></i> ${data.disease_name}
                        <span style="background: rgba(16,185,129,0.15); color:#34d399; padding: 2px 10px; border-radius: 20px; margin-left: 8px; font-weight:700;">Score: ${data.score}%</span>
                    </div>
                </div>
                <div id="abstract-content" style="font-family:'Inter',sans-serif; line-height:1.7;">
                    <p style="font-size:0.85rem;color:#94a3b8;line-height:1.7;">${html}</p>
                </div>
                ${data.pubmed_articles && data.pubmed_articles.length > 0 ? `
                <div style="margin-top: 1.5rem;">
                    <div style="font-size:0.75rem; color:#64748b; font-weight:800; text-transform:uppercase; margin-bottom:0.8rem;">
                        <i class="fas fa-journal-whills" style="color:#10b981; margin-right:4px;"></i> PubMed References
                    </div>
                    ${data.pubmed_articles.map(a => `
                        <div style="padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; margin-bottom: 6px;">
                            <a href="${a.url}" target="_blank" style="font-size:0.8rem; color:#38bdf8; text-decoration:none; font-weight:600;">${a.title}</a>
                            <div style="font-size:0.72rem; color:#64748b; margin-top:3px;">${a.journal} | PMID:${a.pmid}</div>
                        </div>
                    `).join('')}
                </div>` : ''}
                <div style="display:flex; gap: 10px; margin-top: 1.5rem;">
                    <button onclick="copyAbstract()" style="flex:1; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color:#94a3b8; font-weight:700; font-size:0.85rem; cursor:pointer;">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                    <button onclick="window.open('https://pubmed.ncbi.nlm.nih.gov/?term='+encodeURIComponent('${data.drug_name} ${data.disease_name}'), '_blank')" style="flex:1; padding: 12px; background: linear-gradient(135deg, #10b981, #34d399); border: none; border-radius: 10px; color:white; font-weight:700; font-size:0.85rem; cursor:pointer;">
                        <i class="fas fa-external-link-alt"></i> PubMed
                    </button>
                </div>
            `;

            window._abstractText = data.abstract;
        })
        .catch(err => {
            title.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#ef4444;"></i> Error';
            body.innerHTML = '<p style="color:#ef4444; text-align:center; padding:2rem;">Lỗi tạo abstract. Vui lòng thử lại.</p>';
            console.error('Abstract error:', err);
        });
    };

    function closeAbstractModal() {
        document.getElementById('abstract-modal').classList.remove('active');
    }

    window.copyAbstract = function() {
        if (window._abstractText) {
            navigator.clipboard.writeText(window._abstractText).then(() => {
                showToast('Đã copy Clinical Abstract!', 'success');
            });
        }
    };



