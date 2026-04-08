import{n as _e,x as xe,r as v,j as A,s as Se,y as Ae,b as we,o as w,d as k,e as s,t as n,l as V,g as M,F as ke,m as Te,f as Ce,w as De,v as Ee}from"./vendor-89c651cf.js";import{p as Pe}from"./paymentService-cce98883.js";import{_ as Ie,u as $e}from"./index-25dcda42.js";const J={async printThermalTicket(o){try{const a=window.open("","","width=400,height=600");if(!a)return console.error("No se pudo abrir la ventana de impresión"),!1;const p=this.generateThermalHTML(o);return a.document.write(p),a.document.close(),a.onload=()=>{a.print(),setTimeout(()=>{a.close()},1e3)},!0}catch(a){return console.error("Error al imprimir:",a),!1}},async printMultipleTickets(o){try{const a=window.open("","","width=400,height=600");if(!a)return console.error("No se pudo abrir la ventana de impresión"),!1;let p=`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <style>
            ${this.getThermalStyles()}
          </style>
        </head>
        <body>
      `;return o.forEach((C,R)=>{p+=this.generateThermalTicketContent(C),R<o.length-1&&(p+='<div style="page-break-after: always; margin-top: 20px;"></div>')}),p+="</body></html>",a.document.write(p),a.document.close(),a.onload=()=>{a.print(),setTimeout(()=>{a.close()},1e3)},!0}catch(a){return console.error("Error al imprimir múltiples tickets:",a),!1}},generateThermalHTML(o){const a=this.generateThermalTicketContent(o);return`
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <style>
          ${this.getThermalStyles()}
        </style>
      </head>
      <body>
        ${a}
      </body>
      </html>
    `},generateThermalTicketContent(o){const p=new Date().toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"});return`
      <div class="thermal-ticket">
        <div class="thermal-header">
          <div class="cinema-name">CINEA</div>
          <div class="cinema-subtitle">Cines Independientes</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>

        <div class="thermal-section">
          <div class="thermal-label">PELÍCULA:</div>
          <div class="thermal-value bold">${this.truncateText(o.movieTitle||"N/A",32)}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-row">
            <div class="thermal-col">
              <div class="thermal-label">FECHA:</div>
              <div class="thermal-value">${o.screeningDate||"N/A"}</div>
            </div>
            <div class="thermal-col">
              <div class="thermal-label">HORA:</div>
              <div class="thermal-value">${o.screeningTime||"N/A"}</div>
            </div>
          </div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ASIENTO:</div>
          <div class="thermal-value bold">${o.seatNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ENTRADA:</div>
          <div class="thermal-barcode">${o.ticketNumber||"N/A"}</div>
          <div class="thermal-code-small">${o.ticketNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">PRECIO:</div>
          <div class="thermal-value bold">${o.price||"0.00"} €</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>

        <div class="thermal-footer">
          <div class="thermal-small">Impreso: ${p}</div>
          <div class="thermal-small">Presenta este código en la entrada</div>
          <div class="thermal-small">Válido solo para la función indicada</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>
      </div>
    `},getThermalStyles(){return`
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: 'Courier New', monospace;
        width: 80mm;
        padding: 0;
        margin: 0;
        background: white;
      }

      @page {
        size: 80mm 200mm;
        margin: 0;
        padding: 0;
      }

      .thermal-ticket {
        width: 100%;
        padding: 2mm 2mm;
        text-align: center;
        font-size: 10pt;
        line-height: 1.3;
      }

      .thermal-header {
        margin-bottom: 5mm;
        padding-bottom: 3mm;
        border-bottom: 1px solid #000;
      }

      .cinema-name {
        font-size: 18pt;
        font-weight: bold;
        letter-spacing: 2px;
        margin-bottom: 2mm;
      }

      .cinema-subtitle {
        font-size: 8pt;
        color: #333;
      }

      .thermal-divider {
        text-align: center;
        font-size: 9pt;
        margin: 3mm 0;
        letter-spacing: 1px;
      }

      .thermal-section {
        margin: 3mm 0;
        text-align: left;
        padding: 0 2mm;
      }

      .thermal-label {
        font-size: 8pt;
        font-weight: bold;
        color: #333;
        margin-bottom: 1mm;
      }

      .thermal-value {
        font-size: 10pt;
        word-break: break-word;
      }

      .thermal-value.bold {
        font-weight: bold;
        font-size: 11pt;
      }

      .thermal-row {
        display: flex;
        gap: 5mm;
      }

      .thermal-col {
        flex: 1;
      }

      .thermal-barcode {
        font-family: 'Code 128', 'Courier New', monospace;
        font-size: 20pt;
        font-weight: bold;
        letter-spacing: 2px;
        margin: 2mm 0;
        word-break: break-all;
      }

      .thermal-code-small {
        font-size: 8pt;
        letter-spacing: 1px;
        word-break: break-all;
      }

      .thermal-footer {
        margin-top: 3mm;
        padding-top: 2mm;
        border-top: 1px solid #000;
      }

      .thermal-small {
        font-size: 8pt;
        color: #555;
        margin: 1mm 0;
      }

      @media print {
        body {
          margin: 0;
          padding: 0;
        }
        .thermal-ticket {
          page-break-after: always;
        }
      }
    `},truncateText(o,a){return o.length<=a?o:o.substring(0,a-3)+"..."},getTicketData(o){const a=localStorage.getItem("tickets");if(a)try{const C=JSON.parse(a).find(R=>R.ticketNumber===o);if(C)return C}catch(p){console.error("Error al leer tickets del localStorage:",p)}return{ticketNumber:o,movieTitle:"Película",screeningDate:new Date().toLocaleDateString("es-ES"),screeningTime:"20:00",seatNumber:"N/A",price:"10.00"}}};const Re={class:"payment-success-page"},ze={class:"container"},Le={class:"success-card"},Fe={key:0,class:"loading-message"},Oe={key:1,class:"loading-error"},Me={key:2,class:"desk-assistance"},Ue={key:3,class:"ticket-display"},Be={class:"ticket-section"},je={class:"info-grid order-grid"},qe={class:"info-item"},He={class:"info-value"},Ve={class:"info-item"},Je={class:"info-value"},We={class:"info-item"},Ye={class:"info-value"},Ge={class:"info-item"},Ke={class:"info-value"},Qe={class:"info-item"},Xe={class:"info-value"},Ze={class:"info-item"},et={class:"info-value"},tt={class:"info-item"},st={class:"info-value"},at={class:"info-item"},nt={class:"info-value"},rt={class:"ticket-list-section"},ot={class:"tickets-list"},it={class:"ticket-row"},lt={class:"info-value code"},dt={class:"ticket-row"},ut={class:"info-value"},ct={class:"ticket-row"},mt={class:"info-value"},pt={class:"ticket-row"},vt={class:"info-value"},gt={class:"purchase-details"},ft={class:"detail-row"},bt={class:"code"},ht={class:"detail-row"},yt={class:"code"},Nt={class:"detail-row"},_t={class:"detail-row"},xt={class:"amount"},St={class:"detail-row"},At={class:"status-badge status-success"},wt={class:"next-steps"},kt={class:"auto-redirect-note"},Tt={class:"action-buttons"},Ct=["disabled"],Dt={key:0},Et={key:1},Pt=["disabled"],Q=120,It={__name:"PaymentSuccess",setup(o){const a=_e(),p=xe(),C=$e(),R=v(""),E=v(""),g=v(null),m=v([]),U=v(!1),W=v(!1),P=v(""),N=v(""),T=v("info"),d=v(null),D=v(null),_=v(!1),z=v(Q);let B=null,j=null;const X=t=>{if(!t)return"N/A";const e=new Date(t);return Number.isNaN(e.getTime())?"N/A":e.toLocaleDateString("es-AR")},Z=t=>{if(!t)return"N/A";const e=new Date(t);return Number.isNaN(e.getTime())?"N/A":e.toLocaleTimeString("es-AR",{hour:"2-digit",minute:"2-digit"})},se=t=>{if(typeof t=="boolean")return t;if(typeof t=="number")return t===1;if(typeof t=="string"){const e=t.trim().toLowerCase();return["1","true","yes","si","sí"].includes(e)}return!1},ae=t=>se(t==null?void 0:t.non_number)?"S/N":(t==null?void 0:t.seat_code)||`F${(t==null?void 0:t.row_number)||""}-S${(t==null?void 0:t.seat_number)||""}`,ne=(t=[])=>{const e=t.map(i=>ae(i)).filter(Boolean);if(!e.length)return"N/A";const r=[...new Set(e)];return r.length===1&&r[0]==="S/N"&&e.length>1?`S/N x${e.length}`:e.join(", ")},re=(t,e)=>{var x,b,f,h;const r=Array.isArray(t==null?void 0:t.details)?t.details:[],i=String((t==null?void 0:t.status)||"").toLowerCase(),l=i==="confirmed"?"Confirmado":i==="pending"?"Pendiente":i==="cancelled"?"Cancelado":"Pendiente";return{ticketNumber:String((t==null?void 0:t.ticket_number)||(t==null?void 0:t.id)||"N/A"),movieTitle:String(((b=(x=e==null?void 0:e.screening)==null?void 0:x.movie)==null?void 0:b.title)||"Película"),screeningDate:X((f=e==null?void 0:e.screening)==null?void 0:f.start_time),screeningTime:Z((h=e==null?void 0:e.screening)==null?void 0:h.start_time),seatNumber:ne(r),price:String((t==null?void 0:t.price)||"0.00"),statusText:l}},oe=t=>{const e=String(t||"").toLowerCase();return e==="completed"||e==="approved"?"Completado":e==="processing"||e==="pending"?"Procesando":e==="rejected"||e==="declined"?"Rechazado":"Completado"},ie=A(()=>{var r,i;const t=((r=D.value)==null?void 0:r.paidAt)||((i=D.value)==null?void 0:i.updatedAt);return(t?new Date(t):new Date).toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"})}),I=A(()=>{var t;return((t=d.value)==null?void 0:t.orderNumber)||E.value||"N/A"}),ee=A(()=>{var e,r;const t=Number(((e=D.value)==null?void 0:e.amount)??((r=d.value)==null?void 0:r.totalAmount));return Number.isFinite(t)?t.toFixed(2):"No disponible"}),q=A(()=>m.value.length>0||!!g.value),le=A(()=>{const t=m.value.length||(g.value?1:0);return String(t)}),de=A(()=>m.value.length>1?"Imprimir Entradas":"Imprimir Entrada"),ue=A(()=>m.value.length>1?"Descargar Entradas":"Descargar Entrada"),Y=A(()=>{var t,e;return((t=D.value)==null?void 0:t.currency)||((e=d.value)==null?void 0:e.currency)||"ARS"}),ce=A(()=>{var t;return oe((t=D.value)==null?void 0:t.status)}),me=A(()=>{const t=Math.max(0,Number(z.value)||0),e=String(Math.floor(t/60)).padStart(2,"0"),r=String(t%60).padStart(2,"0");return`${e}:${r}`}),te=t=>String(t||"").trim(),pe=t=>{var i,l;const e=te((i=C.currentSession)==null?void 0:i.order_number),r=te(t||E.value||((l=d.value)==null?void 0:l.orderNumber));!e||!r||e===r&&(C.clearCart(),C.clearPaymentSession())},H=()=>{B&&(clearInterval(B),B=null),j&&(clearTimeout(j),j=null)},ve=()=>{H(),z.value=Q,B=setInterval(()=>{if(z.value<=1){z.value=0,H();return}z.value-=1},1e3),j=setTimeout(async()=>{H(),await p.push("/")},Q*1e3)};Se(async()=>{var t;if(ve(),R.value=a.query.ticket||a.params.ticket||"N/A",E.value=a.query.order||a.params.order||"",d.value={orderNumber:String(E.value||"N/A"),currency:"ARS",movieTitle:"",cinemaName:"",roomName:"",format:"",screeningDate:"",screeningTime:""},!E.value){_.value=!0,P.value="Falta el número de orden en la URL. Continúa en ventanilla.";return}await be(),pe((t=d.value)==null?void 0:t.orderNumber),window.scrollTo(0,0)}),Ae(()=>{H()});const ge=(t=[])=>{if(t.length)try{const e=localStorage.getItem("tickets"),r=e?JSON.parse(e):[],i=new Map(r.map(l=>[l.ticketNumber,l]));for(const l of t)l!=null&&l.ticketNumber&&i.set(l.ticketNumber,l);localStorage.setItem("tickets",JSON.stringify(Array.from(i.values())))}catch(e){console.warn("No se pudo persistir tickets en localStorage:",e)}},fe=async()=>{if(!m.value.length&&!g.value)return;const t=m.value.length?m.value:[g.value];let e=!1;t.length>1?e=await J.printMultipleTickets(t):e=await J.printThermalTicket(t[0]),e?(T.value="success",N.value="Impresión automática iniciada."):(T.value="error",N.value='No se pudo imprimir automáticamente. Usa el botón "Imprimir Entrada".')},be=async()=>{var t,e,r,i,l,x,b,f,h,y,L;W.value=!0,P.value="",_.value=!1;try{const S=await Pe.getPaymentOrderDetails(E.value)||{};if(!S.success||!S.order)throw new Error("Respuesta inválida del endpoint de detalle de orden");const u=S.order,O=Array.isArray(S.tickets)?S.tickets:[],c=(Array.isArray(S.payments)?S.payments:[])[0]||{},$=Number(u.total_amount??((t=c==null?void 0:c.response_data)==null?void 0:t.amount));if(!Number.isFinite($))throw new Error("Monto no disponible desde backend");if(d.value={orderNumber:String(u.order_number||E.value),currency:String(u.currency||"ARS"),totalAmount:$,movieTitle:String(((r=(e=u.screening)==null?void 0:e.movie)==null?void 0:r.title)||"N/A"),cinemaName:String(((x=(l=(i=u.screening)==null?void 0:i.room)==null?void 0:l.cinema)==null?void 0:x.name)||"N/A"),roomName:String(((f=(b=u.screening)==null?void 0:b.room)==null?void 0:f.name)||"N/A"),format:String(((h=u.screening)==null?void 0:h.format)||"N/A"),screeningDate:X((y=u.screening)==null?void 0:y.start_time),screeningTime:Z((L=u.screening)==null?void 0:L.start_time)},D.value={amount:$,status:c.status||u.status||"completed",paidAt:u.paid_at||c.completed_at||null,updatedAt:c.updated_at||u.updated_at||null,currency:String(u.currency||"ARS")},O.length===0){_.value=!0,P.value="Pago confirmado, pero las entradas aún no están disponibles. Continúa en ventanilla con tu número de orden.",g.value=null,m.value=[],T.value="error",N.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}const K=O.map(Ne=>re(Ne,u));m.value=K,g.value=K[0],ge(K),await fe()}catch(F){console.error("Error al cargar datos del ticket:",F),P.value="Faltan datos del pago o el backend no respondió. Continúa en ventanilla.",_.value=!0,g.value=null,m.value=[],D.value=null,T.value="error",N.value="Impresión deshabilitada: ve a ventanilla con tu número de orden."}finally{W.value=!1}},he=async()=>{if(_.value){T.value="error",N.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}U.value=!0;try{const t=m.value.length?m.value:[g.value];(t.length>1?await J.printMultipleTickets(t):await J.printThermalTicket(t[0]))?(T.value="success",N.value="Impresión iniciada correctamente."):(T.value="error",N.value="No se pudo completar la impresión. Verifica tu impresora.")}catch(t){console.error("Error durante la impresión:",t),T.value="error",N.value="Error al intentar imprimir. Intenta de nuevo."}finally{U.value=!1}},ye=()=>{var h,y,L,F,S,u,O,G;if(!q.value||_.value)return;const t=m.value.length?m.value:[g.value],e=t.map(c=>c==null?void 0:c.ticketNumber).filter(Boolean).join(", "),r=c=>{const $=Number(c);return Number.isFinite($)?`$${$.toFixed(2)}`:"$0.00"},i=t.map(c=>`
      <tr>
        <td style="padding: 10px; border: 1px solid #cbd5e1;">${c.ticketNumber}</td>
        <td style="padding: 10px; border: 1px solid #cbd5e1;">${c.seatNumber}</td>
        <td style="padding: 10px; border: 1px solid #cbd5e1; text-align: right;">${r(c.price)}</td>
      </tr>
    `).join(""),l=`
    <div style="max-width: 760px; margin: 0 auto; padding: 24px; font-family: Arial, sans-serif; color: #0f172a;">
      <div style="border: 2px solid #1e3a8a; border-radius: 12px; overflow: hidden;">
        <div style="background: #1e3a8a; color: #fff; padding: 16px 20px; text-align: center;">
          <h1 style="margin: 0; font-size: 24px;">CINEA - Entradas</h1>
          <p style="margin: 8px 0 0; font-size: 16px; opacity: 0.95;">
            <strong>${((h=d.value)==null?void 0:h.movieTitle)||((y=g.value)==null?void 0:y.movieTitle)||"Película"}</strong>
          </p>
        </div>

        <div style="padding: 18px 20px;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; font-size: 14px;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
              <strong>Número de orden:</strong><br>${I.value}
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
              <strong>Número(s) de ticket:</strong><br>${e||"N/A"}
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
              <strong>Fecha:</strong> ${((L=d.value)==null?void 0:L.screeningDate)||((F=g.value)==null?void 0:F.screeningDate)||"N/A"}<br>
              <strong>Hora:</strong> ${((S=d.value)==null?void 0:S.screeningTime)||((u=g.value)==null?void 0:u.screeningTime)||"N/A"}
            </div>
            <div style="background: #ecfdf5; border: 1px solid #86efac; border-radius: 8px; padding: 10px;">
              <strong>Total:</strong><br>
              <span style="font-size: 18px; font-weight: 700; color: #166534;">
                ${r(((O=D.value)==null?void 0:O.amount)??((G=d.value)==null?void 0:G.totalAmount))}
              </span>
            </div>
          </div>

          <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
              <tr style="background: #e2e8f0;">
                <th style="padding: 10px; border: 1px solid #cbd5e1; text-align: left;">Ticket</th>
                <th style="padding: 10px; border: 1px solid #cbd5e1; text-align: left;">Asiento(s)</th>
                <th style="padding: 10px; border: 1px solid #cbd5e1; text-align: right;">Precio</th>
              </tr>
            </thead>
            <tbody>
              ${i}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  `,x=new Blob([l],{type:"text/html"}),b=window.URL.createObjectURL(x),f=document.createElement("a");f.href=b,f.download=`entrada_${I.value}.html`,f.click(),window.URL.revokeObjectURL(b)};return(t,e)=>{var i,l,x,b,f,h;const r=we("router-link");return w(),k("div",Re,[s("div",ze,[s("div",Le,[e[31]||(e[31]=s("div",{class:"success-header"},[s("div",{class:"success-icon-large icon-success"}),s("h1",null,"¡Pago Exitoso!"),s("p",{class:"success-subtitle"},"Tu compra ha sido procesada correctamente")],-1)),W.value?(w(),k("p",Fe,"Cargando información de la compra...")):P.value?(w(),k("p",Oe,n(P.value),1)):V("",!0),_.value?(w(),k("div",Me,[e[1]||(e[1]=s("h3",null,"Atención en ventanilla requerida",-1)),e[2]||(e[2]=s("p",null,"No pudimos validar todos los datos del pago en línea.",-1)),s("p",null,[e[0]||(e[0]=s("strong",null,"Orden:",-1)),M(" "+n(I.value),1)]),e[3]||(e[3]=s("p",null,"Acércate a ventanilla con este número para finalizar la entrega de entradas.",-1))])):V("",!0),q.value&&!_.value?(w(),k("div",Ue,[s("div",Be,[e[12]||(e[12]=s("h3",null,"Datos de la Orden",-1)),s("div",je,[s("div",qe,[e[4]||(e[4]=s("span",{class:"info-label"},"Película:",-1)),s("span",He,n(((i=d.value)==null?void 0:i.movieTitle)||"N/A"),1)]),s("div",Ve,[e[5]||(e[5]=s("span",{class:"info-label"},"Cine:",-1)),s("span",Je,n(((l=d.value)==null?void 0:l.cinemaName)||"N/A"),1)]),s("div",We,[e[6]||(e[6]=s("span",{class:"info-label"},"Sala:",-1)),s("span",Ye,n(((x=d.value)==null?void 0:x.roomName)||"N/A"),1)]),s("div",Ge,[e[7]||(e[7]=s("span",{class:"info-label"},"Formato:",-1)),s("span",Ke,n(((b=d.value)==null?void 0:b.format)||"N/A"),1)]),s("div",Qe,[e[8]||(e[8]=s("span",{class:"info-label"},"Fecha:",-1)),s("span",Xe,n(((f=d.value)==null?void 0:f.screeningDate)||"N/A"),1)]),s("div",Ze,[e[9]||(e[9]=s("span",{class:"info-label"},"Hora:",-1)),s("span",et,n(((h=d.value)==null?void 0:h.screeningTime)||"N/A"),1)]),s("div",tt,[e[10]||(e[10]=s("span",{class:"info-label"},"Entradas:",-1)),s("span",st,n(le.value),1)]),s("div",at,[e[11]||(e[11]=s("span",{class:"info-label"},"Total:",-1)),s("span",nt,n(ee.value)+" "+n(Y.value),1)])])]),s("div",rt,[e[17]||(e[17]=s("h3",null,"Entradas",-1)),s("div",ot,[(w(!0),k(ke,null,Te(m.value,y=>(w(),k("div",{key:y.ticketNumber,class:"ticket-item"},[s("div",it,[e[13]||(e[13]=s("span",{class:"info-label"},"Número:",-1)),s("span",lt,n(y.ticketNumber),1)]),s("div",dt,[e[14]||(e[14]=s("span",{class:"info-label"},"Asiento(s):",-1)),s("span",ut,n(y.seatNumber),1)]),s("div",ct,[e[15]||(e[15]=s("span",{class:"info-label"},"Precio:",-1)),s("span",mt,n(y.price)+" "+n(Y.value),1)]),s("div",pt,[e[16]||(e[16]=s("span",{class:"info-label"},"Estado:",-1)),s("span",vt,n(y.statusText),1)])]))),128))])])])):V("",!0),s("div",gt,[e[23]||(e[23]=s("h3",null,"Detalles de la Compra",-1)),s("div",ft,[e[18]||(e[18]=s("span",null,"Número de Orden:",-1)),s("span",bt,n(I.value),1)]),s("div",ht,[e[19]||(e[19]=s("span",null,"Código de Compra:",-1)),s("span",yt,n(I.value),1)]),s("div",Nt,[e[20]||(e[20]=s("span",null,"Fecha de Compra:",-1)),s("span",null,n(ie.value),1)]),s("div",_t,[e[21]||(e[21]=s("span",null,"Importe Pagado:",-1)),s("span",xt,n(ee.value)+" "+n(Y.value),1)]),s("div",St,[e[22]||(e[22]=s("span",null,"Estado:",-1)),s("span",At,n(ce.value),1)])]),s("div",wt,[e[29]||(e[29]=s("h3",null,"Próximos Pasos",-1)),s("ol",null,[s("li",null,[e[24]||(e[24]=M("Recuerda tu número de orden: ",-1)),s("strong",null,n(I.value),1)]),e[25]||(e[25]=s("li",null,"Recibirás un email de confirmación con tu comprobante",-1)),e[26]||(e[26]=s("li",null,"Puedes imprimir tu entrada desde el botón de abajo",-1)),e[27]||(e[27]=s("li",null,"Presenta tu entrada en la taquilla del cine",-1)),e[28]||(e[28]=s("li",null,"Llega 15 minutos antes de que comience la función",-1))]),s("p",kt," Esta pantalla se cerrará automáticamente en "+n(me.value)+" y te llevaremos al inicio. ",1)]),s("div",Tt,[s("button",{onClick:he,class:"btn btn-primary btn-large",disabled:U.value||_.value||!q.value},[U.value?(w(),k("span",Et,"Imprimiendo...")):(w(),k("span",Dt,n(de.value),1))],8,Ct),s("button",{onClick:ye,class:"btn btn-secondary btn-large",disabled:_.value||!q.value},n(ue.value),9,Pt),Ce(r,{to:"/",class:"btn btn-tertiary btn-large"},{default:De(()=>[...e[30]||(e[30]=[M(" Volver al Inicio ",-1)])]),_:1})]),N.value?(w(),k("p",{key:4,class:Ee(["print-message",T.value])},n(N.value),3)):V("",!0),e[32]||(e[32]=s("div",{class:"contact-section"},[s("h4",null,"¿Necesitas ayuda?"),s("p",null,[M("Contacta con nosotros en "),s("strong",null,"info@cinea.es")]),s("p",null,[M("Teléfono: "),s("strong",null,"+34 91 123 4567")]),s("p",{class:"hours"},"Lun-Dom: 10:00 - 22:00")],-1))])]),e[33]||(e[33]=s("div",{id:"print-frame",style:{display:"none"}},null,-1))])}}},Lt=Ie(It,[["__scopeId","data-v-6ef96448"]]);export{Lt as default};
