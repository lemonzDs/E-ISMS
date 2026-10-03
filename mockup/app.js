'use strict';

// Semua rekod adalah sintetik. Tiada sambungan, kuasa kelulusan atau storan pelayan.
const icons = {
  overview: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
  risks: '<path d="M12 3 21 7v6c0 5-9 9-9 9s-9-4-9-9V7l9-4Z"/><path d="M12 8v5m0 3h.01"/>',
  documents: '<path d="M14 3H5v18h14V8l-5-5Z"/><path d="M14 3v5h5M8 12h8m-8 4h6"/>',
  actions: '<rect x="4" y="4" width="16" height="17" rx="2"/><path d="M9 3h6v3H9zM8 13l3 3 5-6"/>',
  controls: '<path d="M4 5h16M4 12h16M4 19h16"/><circle cx="8" cy="5" r="2" fill="currentColor"/><circle cx="16" cy="12" r="2" fill="currentColor"/><circle cx="10" cy="19" r="2" fill="currentColor"/>',
  reports: '<path d="M4 3v18h17M8 16v-4m5 4V8m5 8V5"/>',
  history: '<path d="M3 11a9 9 0 1 1 2 7M3 5v6h6m3-5v6l4 2"/>',
  arrow: '<path d="M5 12h14m-5-5 5 5-5 5"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  close: '<path d="m6 6 12 12M18 6 6 18"/>',
  search: '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
  calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-13 4h3m3 0h3"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  download: '<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>',
  check: '<path d="m5 12 4 4L19 6"/>',
};
const icon = name => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icons[name] || icons.documents}</svg>`;
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const refDate = '2026-10-02';
const risks = [
  {id:'RSK-2026-001',title:'Akses akaun selepas pertukaran pegawai',asset:'Sistem pengurusan dalaman',owner:'Pemilik Sistem A',unit:'Bahagian Teknologi Maklumat',likelihood:4,impact:4,status:'Dalam rawatan',due:'2026-10-08',description:'Akaun pengguna berpotensi kekal aktif selepas pertukaran tugas jika pemakluman dan semakan akses tidak diselaraskan.',treatment:'Semak akaun aktif, padankan dengan senarai pegawai dan rekodkan pengesahan pemilik sistem.'},
  {id:'RSK-2026-002',title:'Pemulihan sandaran belum diuji',asset:'Pangkalan data aplikasi',owner:'Pentadbir Pangkalan Data',unit:'Bahagian Teknologi Maklumat',likelihood:3,impact:5,status:'Dalam rawatan',due:'2026-09-30',description:'Sandaran tersedia tetapi kejayaan pemulihan penuh belum disahkan melalui ujian berjadual.',treatment:'Laksanakan ujian pemulihan di persekitaran berasingan dan simpan laporan hasil ujian.'},
  {id:'RSK-2026-003',title:'Pendedahan dokumen melalui pautan perkongsian',asset:'Repositori dokumen',owner:'Pemilik Dokumen A',unit:'Bahagian Khidmat Pengurusan',likelihood:3,impact:4,status:'Menunggu semakan',due:'2026-10-06',description:'Tetapan perkongsian mungkin membolehkan pihak di luar kumpulan kerja mengakses dokumen.',treatment:'Semak skop perkongsian dan hadkan akses mengikut klasifikasi maklumat.'},
  {id:'RSK-2026-004',title:'Gangguan bekalan kuasa bilik pelayan',asset:'Infrastruktur bilik pelayan',owner:'Pegawai Infrastruktur',unit:'Bahagian Teknologi Maklumat',likelihood:2,impact:5,status:'Dalam rawatan',due:'2026-10-15',description:'Gangguan kuasa boleh menjejaskan ketersediaan perkhidmatan jika bekalan sokongan tidak berfungsi.',treatment:'Semak penyelenggaraan UPS dan kemas kini pelan pemulihan perkhidmatan.'},
  {id:'RSK-2026-005',title:'Kehilangan rekod fizikal semasa pemindahan',asset:'Fail urusan pentadbiran',owner:'Pegawai Rekod',unit:'Bahagian Khidmat Pengurusan',likelihood:2,impact:3,status:'Dipantau',due:'2026-10-20',description:'Pergerakan fail tanpa rekod serahan yang lengkap boleh menyebabkan kehilangan atau salah simpan.',treatment:'Gunakan daftar pergerakan fail dan semak penerimaan oleh pegawai bertanggungjawab.'},
  {id:'RSK-2026-006',title:'Emel penyamaran kepada pegawai',asset:'Perkhidmatan emel',owner:'Pegawai Keselamatan ICT',unit:'Bahagian Teknologi Maklumat',likelihood:4,impact:3,status:'Menunggu semakan',due:'2026-10-09',description:'Pegawai mungkin membuka pautan yang membawa kepada pendedahan maklumat akaun.',treatment:'Jalankan sesi kesedaran dan semak prosedur pelaporan emel mencurigakan.'},
  {id:'RSK-2026-007',title:'Kesilapan penamaan versi dokumen',asset:'Dokumen operasi',owner:'Pemilik Dokumen B',unit:'Bahagian Khidmat Pengurusan',likelihood:2,impact:2,status:'Dipantau',due:'2026-10-22',description:'Penamaan tidak seragam boleh mengakibatkan penggunaan versi dokumen yang salah.',treatment:'Seragamkan rujukan dokumen dan tandakan versi berkuat kuasa.'},
  {id:'RSK-2026-008',title:'Kelewatan pengemaskinian daftar aset',asset:'Daftar aset maklumat',owner:'Pegawai Aset',unit:'Bahagian Khidmat Pengurusan',likelihood:1,impact:2,status:'Draf',due:'2026-10-28',description:'Perubahan pemilik aset belum diselaraskan dalam daftar.',treatment:'Padankan daftar aset dengan rekod serahan terkini.'},
];
const documents = [
  {id:'DOC-001',title:'Polisi keselamatan maklumat',version:'2.0',type:'Polisi',owner:'Penyelaras ISMS',status:'Berkuat kuasa',due:'2026-12-01',content:'Contoh struktur: tujuan, skop, tanggungjawab, pengendalian maklumat dan semakan polisi. Kandungan sebenar perlu datang daripada polisi yang diluluskan.'},
  {id:'DOC-002',title:'Prosedur kawalan akses',version:'1.3',type:'Prosedur',owner:'Pemilik Sistem A',status:'Menunggu semakan',due:'2026-10-05',content:'Draf contoh untuk permohonan akses, semakan pemilik sistem, pemberian akses dan penamatan akaun.'},
  {id:'DOC-003',title:'Prosedur sandaran dan pemulihan',version:'1.1',type:'Prosedur',owner:'Pentadbir Pangkalan Data',status:'Menunggu kelulusan',due:'2026-10-07',content:'Draf contoh meliputi jadual sandaran, lokasi simpanan, pelaksana dan rekod ujian pemulihan.'},
  {id:'DOC-004',title:'Borang penilaian risiko',version:'3.0',type:'Borang',owner:'Penyelaras ISMS',status:'Berkuat kuasa',due:'2026-11-15',content:'Contoh medan: aset/proses, ancaman, kelemahan, kawalan sedia ada, penilaian dan keputusan pemilik risiko.'},
  {id:'DOC-005',title:'Panduan pengendalian dokumen',version:'1.0',type:'Panduan',owner:'Pegawai Rekod',status:'Draf',due:'2026-10-12',content:'Draf contoh untuk klasifikasi, perkongsian, penyimpanan dan pergerakan dokumen.'},
  {id:'DOC-006',title:'Daftar pergerakan fail',version:'1.2',type:'Borang',owner:'Pegawai Rekod',status:'Berkuat kuasa',due:'2026-11-20',content:'Contoh rekod serahan fail: rujukan, penghantar, penerima, tujuan dan tarikh pulangan.'},
];
const actions = [
  {id:'TND-001',title:'Lengkapkan bukti ujian pemulihan sandaran',ref:'RSK-2026-002',owner:'Pentadbir Pangkalan Data',due:'2026-09-30',status:'Dalam tindakan',evidence:'Laporan ujian, masa pemulihan dan pengesahan data.',priority:'Tinggi'},
  {id:'TND-002',title:'Sahkan senarai akaun pegawai bertukar',ref:'RSK-2026-001',owner:'Pemilik Sistem A',due:'2026-10-01',status:'Dalam tindakan',evidence:'Senarai akaun yang disemak dan pengesahan pemilik sistem.',priority:'Tinggi'},
  {id:'TND-003',title:'Semak draf prosedur kawalan akses',ref:'DOC-002',owner:'Penyelaras ISMS',due:'2026-10-05',status:'Ditugaskan',evidence:'Ulasan penyemak pada dokumen versi 1.3.',priority:'Sederhana'},
  {id:'TND-004',title:'Semak akses folder perkongsian',ref:'RSK-2026-003',owner:'Pemilik Dokumen A',due:'2026-10-06',status:'Menunggu pengesahan',evidence:'Senarai kumpulan akses dan hasil semakan tetapan folder (contoh tersedia).',priority:'Tinggi'},
  {id:'TND-005',title:'Kemas kini senarai pemilik aset',ref:'RSK-2026-008',owner:'Pegawai Aset',due:'2026-10-08',status:'Ditugaskan',evidence:'Daftar aset dengan pemilik dan tarikh semakan.',priority:'Rendah'},
  {id:'TND-006',title:'Rekod sesi kesedaran keselamatan',ref:'RSK-2026-006',owner:'Pegawai Keselamatan ICT',due:'2026-09-28',status:'Ditutup',evidence:'Rekod kehadiran contoh dan pengesahan penyelaras.',priority:'Sederhana'},
];
const controls = [
  {id:'KWL-001',title:'Pengurusan akses pengguna',owner:'Pemilik Sistem A',status:'Dalam pelaksanaan',applicable:'Terpakai',reason:'Akses kepada sistem dalaman perlu diberikan mengikut tanggungjawab pegawai.',evidence:'DOC-002 · Prosedur kawalan akses'},
  {id:'KWL-002',title:'Sandaran maklumat',owner:'Pentadbir Pangkalan Data',status:'Dalam pelaksanaan',applicable:'Terpakai',reason:'Ketersediaan maklumat bergantung pada kebolehan pemulihan pangkalan data.',evidence:'DOC-003 · Prosedur sandaran dan pemulihan'},
  {id:'KWL-003',title:'Kesedaran keselamatan maklumat',owner:'Pegawai Keselamatan ICT',status:'Dilaksanakan',applicable:'Terpakai',reason:'Pegawai mengendalikan maklumat organisasi dalam tugasan harian.',evidence:'TND-006 · Rekod sesi kesedaran contoh'},
  {id:'KWL-004',title:'Kawalan dokumen',owner:'Penyelaras ISMS',status:'Dilaksanakan',applicable:'Terpakai',reason:'Versi dokumen dan rekod kelulusan perlu boleh dijejaki.',evidence:'DOC-001 · Polisi keselamatan maklumat'},
  {id:'KWL-005',title:'Perlindungan fizikal bilik pelayan',owner:'Pegawai Infrastruktur',status:'Belum dinilai',applicable:'Belum diputuskan',reason:'Perlu semakan skop lokasi dan kaedah kawalan fizikal yang sebenar.',evidence:'Belum dipautkan'},
  {id:'KWL-006',title:'Pengendalian aset maklumat',owner:'Pegawai Aset',status:'Dalam pelaksanaan',applicable:'Terpakai',reason:'Pemilik dan lokasi aset perlu dikemas kini apabila berlaku perubahan.',evidence:'TND-005 · Semakan daftar aset'},
];
const history = [
  {title:'Prosedur sandaran dihantar untuk kelulusan',by:'Pentadbir Pangkalan Data',time:'2 Okt 2026 · 10:40',ref:'DOC-003'},
  {title:'Penilaian risiko perkongsian dokumen dikemas kini',by:'Pemilik Dokumen A',time:'2 Okt 2026 · 09:15',ref:'RSK-2026-003'},
  {title:'Borang penilaian risiko versi 3.0 diterbitkan',by:'Penyelaras ISMS',time:'1 Okt 2026 · 15:20',ref:'DOC-004'},
  {title:'Bukti semakan akses dihantar untuk pengesahan',by:'Pemilik Dokumen A',time:'1 Okt 2026 · 11:00',ref:'TND-004'},
];
const routes = {overview:'Papan pemuka',risks:'Daftar risiko',documents:'Dokumen terkawal',actions:'Tindakan susulan',controls:'Kawalan & SoA',reports:'Laporan',history:'Jejak audit'};
let overviewTab = 'all';
let toastTimer;
let dialogTrigger;
let riskDraft = {};
const dialog = document.querySelector('#detail-dialog');
const main = document.querySelector('#main');
const E = escapeHtml;
const isLate = a => a.due < refDate && !['Ditutup','Menunggu pengesahan'].includes(a.status);
const activeActions = () => actions.filter(a => a.status !== 'Ditutup');
const riskLevel = r => r.likelihood * r.impact >= 15 ? 'Tinggi' : r.likelihood * r.impact >= 6 ? 'Sederhana' : 'Rendah';
const colorFor = text => /Tinggi|Lewat/.test(text) ? 'danger' : /Sederhana|Menunggu/.test(text) ? 'warning' : /Dalam|Ditugaskan/.test(text) ? 'info' : /Rendah|Berkuat|Ditutup|Dilaksanakan|Dipantau/.test(text) ? 'success' : 'neutral';
const badge = (text, tone) => `<span class="badge ${tone || colorFor(text)}">${E(text)}</span>`;
const displayDate = value => new Intl.DateTimeFormat('ms-MY',{day:'numeric',month:'short',year:'numeric',timeZone:'UTC'}).format(new Date(value+'T12:00:00Z'));
const owner = text => `<span class="owner"><span class="owner-dot" aria-hidden="true">${E(text.split(' ').slice(0,2).map(t=>t[0]).join(''))}</span>${E(text)}</span>`;
function state(){const [page='overview',query=''] = location.hash.slice(1).split('?');return {page:page||'overview',params:new URLSearchParams(query)};}
function url(page,params={}){const query=new URLSearchParams(Object.entries(params).filter(([,v])=>v!==''&&v!=null)).toString();return '#'+page+(query?'?'+query:'');}
function go(page,params={}){location.hash=url(page,params);}
function toast(message){const el=document.querySelector('#toast');el.textContent=message;el.hidden=false;clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.hidden=true,5500);}
function log(title,ref){history.unshift({title,by:'Penyelaras ISMS (démo)',time:'Sesi demonstrasi ini',ref});}
function pageHeader(title,subtitle,actionsHtml=''){return `<div class="page-heading"><div><h1 tabindex="-1">${E(title)}</h1><p>${E(subtitle)}</p></div><div class="heading-actions">${actionsHtml}</div></div>`;}
function recordButton(type,id,title){return `<button class="record-title" data-detail="${type}" data-id="${E(id)}">${E(title)}</button>`;}
function navigation(){document.querySelector('#navigation').innerHTML=Object.entries(routes).map(([key,label],index)=>`${index===5?'<div class="nav-divider"></div>':''}<a class="nav-link ${state().page===key?'active':''}" href="#${key}" ${state().page===key?'aria-current="page"':''}>${icon(key)}<span>${label}</span>${key==='actions'?`<span class="nav-count">${activeActions().length}</span>`:''}</a>`).join('');}
function stat(label,value,note,type,trend=''){return `<a class="stat" href="#${type}"><div class="stat-label">${E(label)}${icon(type)}</div><div class="stat-value"><strong>${value}</strong>${trend?`<span class="trend">${E(trend)}</span>`:''}</div><div class="stat-note">${E(note)}</div></a>`;}
function activityMarkup(items){return items.map(a=>`<div class="activity-item"><span class="dot"></span><div><p>${E(a.title)}</p><small>${E(a.ref)} · ${E(a.by)}</small><small>${E(a.time)}</small></div></div>`).join('');}

function renderOverview(){
  const late=actions.filter(isLate).length;
  const todo=activeActions().filter(a=>overviewTab==='all'||overviewTab==='late'&&isLate(a)||overviewTab==='review'&&a.status==='Menunggu pengesahan');
  const issued=documents.filter(d=>d.status==='Berkuat kuasa').length;
  const review=documents.filter(d=>d.status.startsWith('Menunggu')).length;
  const matrix=[];
  for(let l=5;l>=1;l--){matrix.push(`<span class="axis-number">${l}</span>`);for(let i=1;i<=5;i++){const count=risks.filter(r=>r.likelihood===l&&r.impact===i).length;const score=l*i;matrix.push(`<button class="matrix-cell ${score>=20?'critical':score>=15?'high':score>=6?'medium':'low'}" data-cell="${l},${i}" aria-label="Kebarangkalian ${l}, impak ${i}: ${count} risiko">${count?`<strong>${count}</strong>`:''}</button>`);}}
  main.innerHTML=pageHeader('Gambaran keseluruhan','Status rekod, semakan dan tindakan merentas bahagian.',`<span class="date-chip">${icon('calendar')}2 Oktober 2026</span><button class="button primary" data-action="create-risk">${icon('plus')}Daftar risiko</button>`)+
  `<section class="stats" aria-label="Ringkasan data contoh">${stat('Risiko berdaftar',risks.length,risks.filter(r=>riskLevel(r)==='Tinggi').length+' risiko pada tahap tinggi','risks')}${stat('Dokumen terkawal',documents.length,issued+' versi berkuat kuasa','documents')}${stat('Tindakan aktif',activeActions().length,'Perlu disusuli oleh pemilik','actions',late+' lewat')}${stat('Perlu keputusan',review,'Semakan / kelulusan dokumen','documents')}</section>
  <div class="dashboard-grid"><div class="stack"><section class="panel"><div class="panel-head"><div><h2>Tindakan untuk perhatian <span class="count-dot">${activeActions().length}</span></h2><p>Tarikh sasaran dan pemilik setiap tindakan.</p></div></div><div class="tabs" aria-label="Tapis meja tindakan"><button class="tab" data-task-tab="all" aria-pressed="${overviewTab==='all'}">Semua tindakan</button><button class="tab" data-task-tab="late" aria-pressed="${overviewTab==='late'}">Lewat (${late})</button><button class="tab" data-task-tab="review" aria-pressed="${overviewTab==='review'}">Pengesahan</button></div>
  ${todo.length?todo.map(a=>`<div class="task-row"><span class="task-icon ${isLate(a)?'urgent':''}">${icon(isLate(a)?'clock':'documents')}</span><div class="task-info"><button class="task-title" data-detail="action" data-id="${a.id}">${E(a.title)}</button><div class="task-meta">${badge(isLate(a)?'Lewat':a.status)}<span>${E(a.ref)}</span><span>· ${displayDate(a.due)}</span></div><div class="task-meta">${E(a.owner)}</div></div><button class="button quiet icon-button row-arrow" data-detail="action" data-id="${a.id}" aria-label="Lihat ${E(a.id)}">${icon('arrow')}</button></div>`).join(''):'<div class="empty"><h3>Tiada tindakan dalam kategori ini</h3><p>Semak kategori lain untuk melihat tugasan.</p></div>'}
  <div class="panel-foot"><span>Tarikh rujukan: 2 Okt 2026</span><a class="text-link" href="#actions">Semua tindakan ${icon('arrow')}</a></div></section>
  <section class="panel"><div class="panel-head"><div><h2>Dokumen untuk perhatian</h2><p>Pastikan semakan bergerak mengikut jadual.</p></div><a class="text-link" href="#documents">Lihat semua ${icon('arrow')}</a></div><div class="table-scroll" tabindex="0" role="region" aria-label="Dokumen untuk perhatian"><table><thead><tr><th scope="col">Dokumen</th><th scope="col">Status</th><th scope="col">Sasaran</th></tr></thead><tbody>${documents.filter(d=>d.status.startsWith('Menunggu')).map(d=>`<tr><td>${recordButton('document',d.id,d.title)}<span class="record-id">${d.id} · Versi ${d.version}</span></td><td>${badge(d.status)}</td><td>${displayDate(d.due)}</td></tr>`).join('')}</tbody></table></div></section></div>
  <div class="stack secondary"><section class="panel"><div class="panel-head"><div><h2>Peta risiko</h2><p>Klik petak untuk melihat risiko berkaitan.</p></div>${badge(risks.length+' risiko','neutral')}</div><div class="matrix-wrap"><div class="matrix-layout"><div class="axis-y">Kebarangkalian</div><div><div class="matrix">${matrix.join('')}<span></span>${[1,2,3,4,5].map(i=>`<span class="axis-number">${i}</span>`).join('')}</div><div class="axis-x">Impak</div></div></div><div class="legend"><span><i style="background:#E8F2EE"></i>Rendah</span><span><i style="background:#F7ECCC"></i>Sederhana</span><span><i style="background:#DCACAA"></i>Tinggi</span></div><p class="matrix-note">Matriks 5 × 5 ilustrasi; kaedah sebenar perlu disahkan.</p></div></section>
  <section class="panel"><div class="panel-head"><div><h2>Status dokumen</h2><p>Versi berkuat kuasa dan rekod dalam proses.</p></div></div><div class="progress-section"><div class="progressbar" aria-hidden="true"><span style="width:${issued/documents.length*100}%;background:var(--blue)"></span><span style="width:${review/documents.length*100}%;background:var(--gold)"></span></div>${[['Berkuat kuasa',issued,'var(--blue)'],['Dalam semakan / kelulusan',review,'var(--gold)'],['Draf',documents.length-issued-review,'#CBD5DD']].map(([label,value,color])=>`<div class="progress-row"><span class="dot" style="background:${color}"></span>${label}<strong>${value}</strong></div>`).join('')}<div class="small-notice">${review} dokumen memerlukan keputusan penyemak atau pelulus.</div></div></section>
  <section class="panel"><div class="panel-head"><h2>Aktiviti terkini</h2><a class="text-link" href="#history">Semua ${icon('arrow')}</a></div>${activityMarkup(history.slice(0,2))}</section></div></div>`;
}

const listConfig={
  risks:{title:'Daftar risiko',description:'Kenal pasti, nilai dan susuli risiko keselamatan maklumat.',data:risks,filters:['Semua tahap','Tinggi','Sederhana','Rendah'],headers:['Risiko / aset','Pemilik','Tahap','Status','Tarikh semakan'],row:r=>`<td>${recordButton('risk',r.id,r.title)}<span class="record-id">${r.id} · ${E(r.asset)}</span></td><td>${owner(r.owner)}</td><td>${badge(riskLevel(r))}<span class="record-id">Skor contoh ${r.likelihood*r.impact}</span></td><td>${badge(r.status)}</td><td>${displayDate(r.due)}</td>`,match:(r,f)=>riskLevel(r)===f},
  documents:{title:'Dokumen terkawal',description:'Satu rujukan untuk versi, pemilik dan status dokumen.',data:documents,filters:['Semua status','Berkuat kuasa','Menunggu semakan','Menunggu kelulusan','Draf'],headers:['Dokumen','Jenis','Pemilik','Status','Semakan seterusnya'],row:d=>`<td>${recordButton('document',d.id,d.title)}<span class="record-id">${d.id} · Versi ${d.version}</span></td><td>${E(d.type)}</td><td>${owner(d.owner)}</td><td>${badge(d.status)}</td><td>${displayDate(d.due)}</td>`,match:(r,f)=>r.status===f},
  actions:{title:'Tindakan susulan',description:'Jejaki pelaksana, bukti dan pengesahan setiap tindakan.',data:actions,filters:['Semua status','Lewat','Ditugaskan','Dalam tindakan','Menunggu pengesahan','Ditutup'],headers:['Tindakan','Pelaksana','Tarikh sasaran','Status','Keutamaan'],row:a=>`<td>${recordButton('action',a.id,a.title)}<span class="record-id">${a.id} · ${a.ref}</span></td><td>${owner(a.owner)}</td><td>${displayDate(a.due)}${isLate(a)?'<span class="record-id">'+badge('Lewat','danger')+'</span>':''}</td><td>${badge(a.status)}</td><td>${badge(a.priority)}</td>`,match:(r,f)=>f==='Lewat'?isLate(r):r.status===f},
  controls:{title:'Kawalan & SoA',description:'Rekod keputusan kebolehgunaan kawalan dan bukti pelaksanaan.',data:controls,filters:['Semua status','Dilaksanakan','Dalam pelaksanaan','Belum dinilai'],headers:['Kawalan','Pemilik','Kebolehgunaan','Pelaksanaan'],row:c=>`<td>${recordButton('control',c.id,c.title)}<span class="record-id">${c.id} · Rujukan dalaman contoh</span></td><td>${owner(c.owner)}</td><td>${badge(c.applicable,c.applicable==='Terpakai'?'info':'neutral')}</td><td>${badge(c.status)}</td>`,match:(r,f)=>r.status===f},
};
function filteredRecords(page,params){const c=listConfig[page],q=(params.get('q')||'').toLocaleLowerCase('ms'),f=params.get('filter')||'';return c.data.filter(r=>(!q||Object.values(r).join(' ').toLocaleLowerCase('ms').includes(q))&&(!f||c.match(r,f))&&(!params.get('cell')||`${r.likelihood},${r.impact}`===params.get('cell')));}
function renderList(page){
  const c=listConfig[page],{params}=state();const records=filteredRecords(page,params),size=6,pages=Math.max(1,Math.ceil(records.length/size)),current=Math.max(1,Math.min(pages,Number(params.get('p'))||1)),q=params.get('q')||'',f=params.get('filter')||'';
  main.innerHTML=pageHeader(c.title,c.description,`<button class="button" data-export="${page}">${icon('download')}Eksport CSV</button>${page==='risks'?`<button class="button primary" data-action="create-risk">${icon('plus')}Daftar risiko</button>`:''}`)+
  (page==='controls'?'<div class="notice">SoA = Statement of Applicability. Enam kawalan contoh ini menggunakan rujukan dalaman, bukan senarai penuh atau pemetaan rasmi ISO.</div>':'')+
  (page==='risks'?`<div class="summary-line"><span><strong>${risks.length}</strong> risiko berdaftar</span><span><strong>${risks.filter(r=>riskLevel(r)==='Tinggi').length}</strong> tinggi</span><span><strong>${risks.filter(r=>riskLevel(r)==='Sederhana').length}</strong> sederhana</span><span><strong>${risks.filter(r=>riskLevel(r)==='Rendah').length}</strong> rendah</span><span>Penilaian ilustrasi sahaja</span></div>`:'')+
  `<section class="panel"><form class="toolbar" id="search-form" novalidate><label class="search-wrap">${icon('search')}<input type="search" name="q" aria-label="Cari ${E(c.title.toLowerCase())}" placeholder="Cari rujukan, tajuk atau pemilik…" value="${E(q)}"></label><button class="button" type="submit">Cari</button><label class="filter-label" for="status-filter">Tapis</label><select id="status-filter" aria-label="Tapis ${E(c.title.toLowerCase())}" name="filter">${c.filters.map((v,i)=>`<option value="${i?E(v):''}" ${f===(i?v:'')?'selected':''}>${E(v)}</option>`).join('')}</select>${q||f||params.get('cell')?'<button type="button" class="button quiet" data-action="reset-filter">Kosongkan penapis</button>':''}</form>
  ${params.get('cell')?`<div class="notice" style="margin:15px 22px">Petak terpilih: kebarangkalian ${E(params.get('cell').split(',')[0])}, impak ${E(params.get('cell').split(',')[1])}.</div>`:''}
  <div class="table-scroll" tabindex="0" role="region" aria-label="Jadual ${E(c.title.toLowerCase())}"><table><thead><tr>${c.headers.map(h=>`<th scope="col">${h}</th>`).join('')}</tr></thead><tbody>${records.length?records.slice((current-1)*size,current*size).map(r=>`<tr>${c.row(r)}</tr>`).join(''):`<tr><td colspan="${c.headers.length}" class="empty"><h3>Tiada rekod sepadan</h3><p>Cuba kata carian lain atau kosongkan penapis.</p><button class="button" data-action="reset-filter">Kosongkan penapis</button></td></tr>`}</tbody></table></div>
  <div class="pagination"><span>${records.length?(current-1)*size+1:0}–${Math.min(current*size,records.length)} daripada ${records.length} rekod contoh</span><div class="pagination-controls"><button class="button" data-page="${current-1}" ${current<=1?'disabled':''}>Sebelum</button><span>Halaman ${current} / ${pages}</span><button class="button" data-page="${current+1}" ${current>=pages?'disabled':''}>Seterusnya</button></div></div></section>`;
}

function renderReports(){main.innerHTML=pageHeader('Laporan','Muat turun rekod contoh untuk menyemak bentuk output sistem.')+`<div class="notice">Eksport mengandungi data demonstrasi sahaja. Kawalan akses eksport sebenar akan dilaksanakan pada pelayan Laravel.</div><div class="report-grid">${Object.entries(listConfig).map(([key,c])=>`<section class="panel report-card">${icon(key)}<h2>${E(c.title)}</h2><p>${E(c.description)} Eksport semua ${c.data.length} rekod contoh dalam format CSV.</p><button class="button" data-export="${key}">${icon('download')}Muat turun CSV</button></section>`).join('')}</div>`;}
function renderHistory(){main.innerHTML=pageHeader('Jejak audit','Lihat siapa melakukan perubahan, rekod berkaitan dan masanya.')+'<div class="notice">Sejarah di bawah ialah contoh dan aktiviti sesi mockup. Log audit kekal akan dibina dalam Laravel.</div><section class="panel history-list">'+activityMarkup(history)+'</section>';}
function render(focus=false){
  const {page,params}=state();
  navigation();
  document.querySelector('#breadcrumb').textContent=routes[page]||'Halaman tidak ditemui';
  document.title=`${routes[page]||'Halaman tidak ditemui'} · e-ISMS SUK Pahang`;
  if(page==='overview')renderOverview();
  else if(listConfig[page])renderList(page);
  else if(page==='reports')renderReports();
  else if(page==='history')renderHistory();
  else main.innerHTML=pageHeader('Halaman tidak ditemui','Pautan ini tidak tersedia dalam mockup.')+'<a class="button primary" href="#overview">Kembali ke papan pemuka</a>';
  if(focus)main.querySelector('h1')?.focus({preventScroll:true});
  const record=params.get('record');
  const type={risks:'risk',documents:'document',actions:'action',controls:'control'}[page];
  if(record&&type&&!dialog.open){
    if(listConfig[page].data.some(item=>item.id===record))showDetail(type,record);
    else toast('Rekod contoh tidak ditemui. Pilih rekod daripada senarai.');
  }
}

function openDialog(title,subtitle,body,footer='',form=false){dialogTrigger=document.activeElement;document.querySelector('#dialog-content').innerHTML=`${form?'<form id="risk-form" novalidate>':''}<div class="dialog-head"><div><h2 id="dialog-title">${E(title)}</h2><p>${E(subtitle)}</p></div><button class="button quiet icon-button" type="button" data-action="close-dialog" aria-label="Tutup">${icon('close')}</button></div><div class="dialog-body">${body}</div><div class="dialog-footer">${footer||'<button class="button" type="button" data-action="close-dialog">Tutup</button>'}</div>${form?'</form>':''}`;dialog.showModal();dialog.querySelector(form?'input':'[data-action="close-dialog"]')?.focus();}
function detailGrid(pairs){return `<dl class="detail-grid">${pairs.map(([k,v])=>`<div><dt>${E(k)}</dt><dd>${E(v)}</dd></div>`).join('')}</dl>`;}
function showDetail(type,id){
  if(type==='risk'){const r=risks.find(r=>r.id===id);if(!r)return;openDialog(r.title,`${r.id} · Rekod risiko contoh`,`${badge(riskLevel(r))} ${badge(r.status)}${detailGrid([['Aset / proses',r.asset],['Pemilik risiko',r.owner],['Bahagian',r.unit],['Tarikh semakan',displayDate(r.due)],['Kebarangkalian × impak',`${r.likelihood} × ${r.impact} = ${r.likelihood*r.impact}`],['Risiko baki','Belum dinilai dalam contoh']])}<div class="demo-note">Skor 1–5 dan ambang tahap ialah ilustrasi. Kaedah yang diluluskan SUK Pahang akan digunakan dalam sistem sebenar.</div><section class="detail-section"><h3>Pernyataan risiko</h3><p>${E(r.description)}</p></section><section class="detail-section"><h3>Cadangan rawatan</h3><p>${E(r.treatment)}</p></section><section class="detail-section"><h3>Tindakan berkaitan</h3>${actions.filter(a=>a.ref===id).map(a=>`<p>${E(a.id)} · ${E(a.title)} — ${E(a.status)}</p>`).join('')||'<p>Belum ada tindakan dipautkan.</p>'}</section>`);}
  if(type==='document'){const d=documents.find(d=>d.id===id);if(!d)return;openDialog(d.title,`${d.id} · Versi ${d.version}`,`${badge(d.status)}${detailGrid([['Pemilik',d.owner],['Jenis dokumen',d.type],['Versi semasa',d.version],['Semakan seterusnya',displayDate(d.due)]])}<div class="step-track"><span>Draf</span><span class="${d.status==='Menunggu semakan'?'current':''}">Semakan</span><span class="${d.status==='Menunggu kelulusan'?'current':''}">Kelulusan</span><span class="${d.status==='Berkuat kuasa'?'current':''}">Berkuat kuasa</span></div><section class="detail-section"><h3>Pratonton kandungan contoh</h3><p>${E(d.content)}</p></section><section class="detail-section"><h3>Rekod versi contoh</h3><p>Versi ${d.version} · ${E(d.status)} · ${E(d.owner)}</p><p>Versi terdahulu dan fail sebenar akan dipautkan semasa migrasi.</p></section><div class="small-notice">Tiada fail sebenar dimuat naik. Kelulusan dokumen memerlukan peranan dan aliran rasmi yang disahkan.</div>`);}
  if(type==='action'){const a=actions.find(a=>a.id===id);if(!a)return;openDialog(a.title,`${a.id} · Berkaitan ${a.ref}`,`${badge(a.status)} ${isLate(a)?badge('Lewat','danger'):''}${detailGrid([['Pelaksana',a.owner],['Tarikh sasaran',displayDate(a.due)],['Keutamaan',a.priority],['Rujukan',a.ref]])}<section class="detail-section"><h3>Bukti yang diperlukan</h3><p>${E(a.evidence)}</p></section><div class="small-notice">Butang simulasi menukar status rekod contoh sahaja. Ia tidak memuat naik bukti, menghantar notifikasi atau menutup risiko sebenar.</div>`,`${!['Ditutup','Menunggu pengesahan'].includes(a.status)?`<button class="button primary" data-action="simulate-submit" data-id="${a.id}" type="button">${icon('check')}Simulasi hantar untuk pengesahan</button>`:''}<button type="button" class="button" data-action="close-dialog">Tutup</button>`);}
  if(type==='control'){const c=controls.find(c=>c.id===id);if(!c)return;openDialog(c.title,`${c.id} · Kawalan contoh`,`${badge(c.status)}${detailGrid([['Pemilik kawalan',c.owner],['Kebolehgunaan',c.applicable]])}<section class="detail-section"><h3>Justifikasi</h3><p>${E(c.reason)}</p></section><section class="detail-section"><h3>Bukti dipautkan</h3><p>${E(c.evidence)}</p></section><div class="small-notice">Rujukan ISO, kelulusan kebolehgunaan dan justifikasi pengecualian perlu disahkan sebelum SoA sebenar diterbitkan.</div>`);}
}
function field(name,label,type='text',wide=false,help=''){return `<div class="field ${wide?'wide':''}"><label for="${name}">${label} <span aria-hidden="true">*</span></label><input id="${name}" name="${name}" type="${type}" required maxlength="${name==='title'?160:120}" value="${E(riskDraft[name]||'')}" aria-describedby="${name}-error${help?' '+name+'-help':''}" aria-invalid="false">${help?`<small id="${name}-help">${E(help)}</small>`:''}<div class="error" id="${name}-error"></div></div>`;}
function createRisk(){openDialog('Daftar risiko baharu','Borang demonstrasi · bukan rekod operasi sebenar',`<p class="form-note">Medan bertanda * diperlukan. Gunakan data rekaan sahaja. Draf borang disimpan sepanjang sesi ini.</p><div class="form-grid">${field('title','Tajuk risiko','text',true)}${field('asset','Aset / proses')}${field('owner','Pemilik risiko')}${field('unit','Bahagian','text',true)}<div class="field"><label for="likelihood">Kebarangkalian contoh *</label><select id="likelihood" name="likelihood">${[1,2,3,4,5].map(n=>`<option ${String(riskDraft.likelihood||3)===String(n)?'selected':''}>${n}</option>`).join('')}</select></div><div class="field"><label for="impact">Impak contoh *</label><select id="impact" name="impact">${[1,2,3,4,5].map(n=>`<option ${String(riskDraft.impact||3)===String(n)?'selected':''}>${n}</option>`).join('')}</select></div>${field('due','Tarikh semakan','text',true,'Format DD/MM/YYYY, contoh 15/10/2026.')}<div class="field wide"><label for="description">Pernyataan risiko</label><textarea id="description" name="description" maxlength="1200" placeholder="Terangkan perkara yang mungkin berlaku dan kesannya.">${E(riskDraft.description||'')}</textarea></div></div>`,`<button class="button" type="button" data-action="close-dialog">Batal</button><button class="button primary" type="submit">Simpan risiko contoh</button>`,true);}
function readDraft(){const form=document.querySelector('#risk-form');if(form)riskDraft=Object.fromEntries(new FormData(form));}
function parseDate(value){const m=/^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value);if(!m)return null;const iso=`${m[3]}-${m[2]}-${m[1]}`;const d=new Date(iso+'T12:00:00Z');return !Number.isNaN(d.getTime())&&d.toISOString().slice(0,10)===iso&&+m[3]>=2000&&+m[3]<=2100?iso:null;}
function saveRisk(form){
  readDraft();let first=null;for(const name of ['title','asset','owner','unit','due']){const input=form.elements[name];const value=input.value.trim();const error=!value?'Lengkapkan medan ini.':name==='due'&&!parseDate(value)?'Masukkan tarikh sah dalam format DD/MM/YYYY.':'';input.setAttribute('aria-invalid',String(!!error));document.querySelector(`#${name}-error`).textContent=error;if(error&&!first)first=input;}
  if(first){first.focus();return;}
  const r={id:`RSK-2026-${String(risks.length+1).padStart(3,'0')}`,title:riskDraft.title.trim(),asset:riskDraft.asset.trim(),owner:riskDraft.owner.trim(),unit:riskDraft.unit.trim(),due:parseDate(riskDraft.due.trim()),likelihood:Number(riskDraft.likelihood),impact:Number(riskDraft.impact),status:'Draf',description:riskDraft.description.trim()||'Pernyataan risiko belum dilengkapkan.',treatment:'Pelan rawatan belum disediakan.'};
  risks.unshift(r);log('Risiko contoh didaftarkan',r.id);riskDraft={};dialog.close('saved');go('risks');render(true);toast(`${r.id} disimpan dalam sesi demonstrasi.`);
}
function exportCsv(page){const records=state().page===page?filteredRecords(page,state().params):listConfig[page].data;const headers=Object.keys(listConfig[page].data[0]);const cell=value=>'"'+String(value??'').replace(/^[=+@-]/,"'$&").replace(/"/g,'""')+'"';const csv='\uFEFF'+[headers,...records.map(r=>headers.map(h=>r[h]))].map(row=>row.map(cell).join(',')).join('\r\n');const blobUrl=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));const link=document.createElement('a');link.href=blobUrl;link.download=`CONTOH-eISMS-${page}-2026-10-02.csv`;link.click();setTimeout(()=>URL.revokeObjectURL(blobUrl),1000);toast(`${records.length} rekod contoh dieksport sebagai CSV.`);}

document.addEventListener('click',event=>{
  const target=event.target.closest('button,a');if(!target)return;
  if(target.classList.contains('skip')){event.preventDefault();main.focus();main.scrollIntoView({block:'start'});return;}
  if(target.dataset.detail)showDetail(target.dataset.detail,target.dataset.id);
  if(target.dataset.taskTab){overviewTab=target.dataset.taskTab;renderOverview();main.querySelector(`[data-task-tab="${overviewTab}"]`)?.focus();}
  if(target.dataset.cell)go('risks',{cell:target.dataset.cell});
  if(target.dataset.page){const s=state();s.params.set('p',target.dataset.page);go(s.page,Object.fromEntries(s.params));}
  if(target.dataset.export)exportCsv(target.dataset.export);
  const action=target.dataset.action;
  if(action==='create-risk')createRisk();
  if(action==='close-dialog'){readDraft();dialog.close('cancel');}
  if(action==='reset-filter'){go(state().page);render(true);}
  if(action==='simulate-submit'){const a=actions.find(a=>a.id===target.dataset.id);if(a&&!['Ditutup','Menunggu pengesahan'].includes(a.status)){a.status='Menunggu pengesahan';log('Tindakan dihantar untuk pengesahan (simulasi)',a.id);dialog.close();render(true);toast(`${a.id}: status contoh ditukar kepada Menunggu pengesahan.`);}}
  if(action==='guide')openDialog('Panduan review mockup','Paparan Penyelaras ISMS',`<p>Gunakan menu untuk menyemak aliran kerja yang dicadangkan.</p><section class="detail-section"><h3>Cuba aliran utama</h3><ol><li>Buka tindakan lewat pada papan pemuka.</li><li>Klik petak peta risiko untuk menapis daftar.</li><li>Daftar risiko contoh dan semak validasi borang.</li><li>Buka dokumen untuk melihat versi dan status.</li><li>Cuba simulasi penghantaran tindakan, kemudian lihat jejak audit.</li><li>Eksport CSV contoh melalui halaman laporan.</li></ol></section><div class="demo-note">Mockup ini belum menggunakan Laravel, login atau pangkalan data. Muat semula halaman untuk kembali kepada data asal. Jangan masukkan maklumat sebenar atau sensitif.</div><section class="detail-section"><h3>Perkara untuk maklum balas</h3><p>Susunan menu, medan risiko, langkah semakan, keterbacaan jadual serta tindakan harian yang masih belum diwakili. Selepas review, reka bentuk diterjemahkan kepada Laravel 13 di Laragon.</p></section>`);
});
document.addEventListener('submit',event=>{if(event.target.id==='search-form'){event.preventDefault();const s=state();const data=new FormData(event.target);go(s.page,{q:String(data.get('q')).trim(),filter:data.get('filter'),cell:s.params.get('cell')});}if(event.target.id==='risk-form'){event.preventDefault();saveRisk(event.target);}});
document.addEventListener('change',event=>{if(event.target.id==='status-filter'){const form=event.target.closest('form'),s=state();go(s.page,{q:form.elements.q.value.trim(),filter:event.target.value,cell:s.params.get('cell')});}});
dialog.addEventListener('cancel',event=>{event.preventDefault();readDraft();dialog.close('cancel');});
dialog.addEventListener('close',()=>{
  const current=state();
  if(current.params.has('record')){current.params.delete('record');historyApiReplace(url(current.page,Object.fromEntries(current.params)));}
  if(dialog.returnValue==='cancel'&&Object.values(riskDraft).some(Boolean))toast('Draf dikekalkan dalam sesi ini. Buka Daftar risiko untuk sambung.');
  if(dialogTrigger?.isConnected)dialogTrigger.focus({preventScroll:true});
});
function historyApiReplace(hash){window.history.replaceState(null,'',hash);}
window.addEventListener('hashchange',()=>{render(true);window.scrollTo({top:0,behavior:'instant'});});
render();
