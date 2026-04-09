import{p as _e,x as Se,r as p,k as w,s as we,y as Ae,b as ke,o as A,d as k,e as a,t as n,m as Y,g as B,F as Te,n as Ce,f as De,w as Ee,v as Ie}from"./vendor-8f3c8a00.js";import{_ as Pe,u as Re,p as $e}from"./index-ec99a171.js";const G={async printThermalTicket(i){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;const m=this.generateThermalHTML(i);return s.document.write(m),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir:",s),!1}},async printMultipleTickets(i){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;let m=`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <style>
            ${this.getThermalStyles()}
          </style>
        </head>
        <body>
      `;return i.forEach((D,F)=>{m+=this.generateThermalTicketContent(D),F<i.length-1&&(m+='<div class="ticket-break"></div>')}),m+="</body></html>",s.document.write(m),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir múltiples tickets:",s),!1}},generateThermalHTML(i){const s=this.generateThermalTicketContent(i);return`
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <style>
          ${this.getThermalStyles()}
        </style>
      </head>
      <body>
        ${s}
      </body>
      </html>
    `},generateThermalTicketContent(i){const m=new Date().toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"});return`
      <div class="thermal-ticket">
        <div class="thermal-header">
          <div class="cinema-name">CINEA</div>
          <div class="cinema-subtitle">Cines Independientes</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>

        <div class="thermal-section">
          <div class="thermal-label">PELÍCULA:</div>
          <div class="thermal-value bold">${this.truncateText(i.movieTitle||"N/A",32)}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-row">
            <div class="thermal-col">
              <div class="thermal-label">FECHA:</div>
              <div class="thermal-value">${i.screeningDate||"N/A"}</div>
            </div>
            <div class="thermal-col">
              <div class="thermal-label">HORA:</div>
              <div class="thermal-value">${i.screeningTime||"N/A"}</div>
            </div>
          </div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ASIENTO:</div>
          <div class="thermal-value bold">${i.seatNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ENTRADA:</div>
          <div class="thermal-barcode">${i.ticketNumber||"N/A"}</div>
          <div class="thermal-code-small">${i.ticketNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">PRECIO:</div>
          <div class="thermal-value bold">${i.price||"0.00"} €</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>

        <div class="thermal-footer">
          <div class="thermal-small">Impreso: ${m}</div>
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
        width: 72mm;
        max-width: 72mm;
        margin: 0 auto;
        padding: 1.5mm 0 0;
        background: white;
        color: #000;
      }

      @page {
        size: 80mm auto;
        margin: 1.5mm;
      }

      .thermal-ticket {
        width: 100%;
        padding: 1.5mm 1mm 2mm;
        text-align: center;
        font-size: 10pt;
        line-height: 1.2;
      }

      .thermal-header {
        margin-bottom: 3.2mm;
        padding-bottom: 2mm;
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
        margin: 2mm 0;
        letter-spacing: 1px;
      }

      .thermal-section {
        margin: 2.2mm 0;
        text-align: left;
        padding: 0 0.8mm;
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
        gap: 2mm;
      }

      .thermal-col {
        flex: 1;
      }

      .thermal-barcode {
        font-family: 'Code 128', 'Courier New', monospace;
        font-size: 20pt;
        font-weight: bold;
        letter-spacing: 1.3px;
        margin: 1.2mm 0;
        word-break: break-all;
      }

      .thermal-code-small {
        font-size: 8pt;
        letter-spacing: 1px;
        word-break: break-all;
      }

      .thermal-footer {
        margin-top: 2.2mm;
        padding-top: 1.5mm;
        border-top: 1px solid #000;
      }

      .thermal-small {
        font-size: 8pt;
        color: #555;
        margin: 1mm 0;
      }

      .ticket-break {
        height: 3mm;
        page-break-after: always;
      }

      @media print {
        body {
          width: 72mm;
          max-width: 72mm;
          margin: 0 auto;
          padding: 0;
        }
      }
    `},truncateText(i,s){return i.length<=s?i:i.substring(0,s-3)+"..."},getTicketData(i){const s=localStorage.getItem("tickets");if(s)try{const D=JSON.parse(s).find(F=>F.ticketNumber===i);if(D)return D}catch(m){console.error("Error al leer tickets del localStorage:",m)}return{ticketNumber:i,movieTitle:"Película",screeningDate:new Date().toLocaleDateString("es-ES"),screeningTime:"20:00",seatNumber:"N/A",price:"10.00"}}};const ze={class:"payment-success-page"},Le={class:"container"},Fe={class:"success-card"},Oe={key:0,class:"loading-message"},Ue={key:1,class:"loading-error"},Me={key:2,class:"desk-assistance"},Be={key:3,class:"ticket-display"},He={class:"ticket-section"},je={class:"info-grid order-grid"},qe={class:"info-item"},Ve={class:"info-value"},Je={class:"info-item"},We={class:"info-value"},Ye={class:"info-item"},Ge={class:"info-value"},Ke={class:"info-item"},Qe={class:"info-value"},Xe={class:"info-item"},Ze={class:"info-value"},et={class:"info-item"},tt={class:"info-value"},at={class:"info-item"},st={class:"info-value"},nt={class:"info-item"},ot={class:"info-value"},rt={class:"ticket-list-section"},it={class:"tickets-list"},lt={class:"ticket-row"},dt={class:"info-value code"},ct={class:"ticket-row"},ut={class:"info-value"},mt={class:"ticket-row"},pt={class:"info-value"},vt={class:"ticket-row"},gt={class:"info-value"},ft={class:"purchase-details"},bt={class:"detail-row"},ht={class:"code"},yt={class:"detail-row"},Nt={class:"code"},xt={class:"detail-row"},_t={class:"detail-row"},St={class:"amount"},wt={class:"detail-row"},At={class:"status-badge status-success"},kt={class:"next-steps"},Tt={class:"auto-redirect-note"},Ct={class:"action-buttons"},Dt=["disabled"],Et={key:0},It={key:1},Pt=["disabled"],X=120,Rt={__name:"PaymentSuccess",setup(i){const s=_e(),m=Se(),D=Re(),F=p(""),I=p(""),v=p(null),u=p([]),H=p(!1),K=p(!1),R=p(""),h=p(""),T=p("info"),d=p(null),E=p(null),y=p(!1),O=p(X);let j=null,q=null;const Z=t=>{if(!t)return"N/A";const e=new Date(t);return Number.isNaN(e.getTime())?"N/A":e.toLocaleDateString("es-AR")},ee=t=>{if(!t)return"N/A";const e=new Date(t);return Number.isNaN(e.getTime())?"N/A":e.toLocaleTimeString("es-AR",{hour:"2-digit",minute:"2-digit"})},ne=t=>{if(typeof t=="boolean")return t;if(typeof t=="number")return t===1;if(typeof t=="string"){const e=t.trim().toLowerCase();return["1","true","yes","si","sí"].includes(e)}return!1},oe=t=>ne(t==null?void 0:t.non_number)?"S/N":(t==null?void 0:t.seat_code)||`F${(t==null?void 0:t.row_number)||""}-S${(t==null?void 0:t.seat_number)||""}`,re=(t=[])=>{const e=t.map(r=>oe(r)).filter(Boolean);if(!e.length)return"N/A";const o=[...new Set(e)];return o.length===1&&o[0]==="S/N"&&e.length>1?`S/N x${e.length}`:e.join(", ")},ie=(t,e)=>{var g,N,x,_;const o=Array.isArray(t==null?void 0:t.details)?t.details:[],r=String((t==null?void 0:t.status)||"").toLowerCase(),l=r==="confirmed"?"Confirmado":r==="pending"?"Pendiente":r==="cancelled"?"Cancelado":"Pendiente";return{ticketNumber:String((t==null?void 0:t.ticket_number)||(t==null?void 0:t.id)||"N/A"),movieTitle:String(((N=(g=e==null?void 0:e.screening)==null?void 0:g.movie)==null?void 0:N.title)||"Película"),screeningDate:Z((x=e==null?void 0:e.screening)==null?void 0:x.start_time),screeningTime:ee((_=e==null?void 0:e.screening)==null?void 0:_.start_time),seatNumber:re(o),price:String((t==null?void 0:t.price)||"0.00"),statusText:l}},le=t=>{const e=String(t||"").toLowerCase();return e==="completed"||e==="approved"?"Completado":e==="processing"||e==="pending"?"Procesando":e==="rejected"||e==="declined"?"Rechazado":"Completado"},de=w(()=>{var o,r;const t=((o=E.value)==null?void 0:o.paidAt)||((r=E.value)==null?void 0:r.updatedAt);return(t?new Date(t):new Date).toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"})}),P=w(()=>{var t;return((t=d.value)==null?void 0:t.orderNumber)||I.value||"N/A"}),te=w(()=>{var e,o;const t=Number(((e=E.value)==null?void 0:e.amount)??((o=d.value)==null?void 0:o.totalAmount));return Number.isFinite(t)?t.toFixed(2):"No disponible"}),V=w(()=>u.value.length>0||!!v.value),ce=w(()=>{const t=u.value.length||(v.value?1:0);return String(t)}),ue=w(()=>u.value.length>1?"Imprimir Entradas":"Imprimir Entrada"),me=w(()=>u.value.length>1?"Descargar Entradas":"Descargar Entrada"),J=w(()=>{var t,e;return((t=E.value)==null?void 0:t.currency)||((e=d.value)==null?void 0:e.currency)||"ARS"}),pe=w(()=>{var t;return le((t=E.value)==null?void 0:t.status)}),ve=w(()=>{const t=Math.max(0,Number(O.value)||0),e=String(Math.floor(t/60)).padStart(2,"0"),o=String(t%60).padStart(2,"0");return`${e}:${o}`}),ae=t=>String(t||"").trim(),ge=t=>{var r,l;const e=ae((r=D.currentSession)==null?void 0:r.order_number),o=ae(t||I.value||((l=d.value)==null?void 0:l.orderNumber));!e||!o||e===o&&(D.clearCart(),D.clearPaymentSession())},W=()=>{j&&(clearInterval(j),j=null),q&&(clearTimeout(q),q=null)},fe=()=>{W(),O.value=X,j=setInterval(()=>{if(O.value<=1){O.value=0,W();return}O.value-=1},1e3),q=setTimeout(async()=>{W(),await m.push("/")},X*1e3)};we(async()=>{var t;if(fe(),F.value=s.query.ticket||s.params.ticket||"N/A",I.value=s.query.order||s.params.order||"",d.value={orderNumber:String(I.value||"N/A"),currency:"ARS",movieTitle:"",cinemaName:"",roomName:"",format:"",screeningDate:"",screeningTime:""},!I.value){y.value=!0,R.value="Falta el número de orden en la URL. Continúa en ventanilla.";return}await ye(),ge((t=d.value)==null?void 0:t.orderNumber),window.scrollTo(0,0)}),Ae(()=>{W()});const be=(t=[])=>{if(t.length)try{const e=localStorage.getItem("tickets"),o=e?JSON.parse(e):[],r=new Map(o.map(l=>[l.ticketNumber,l]));for(const l of t)l!=null&&l.ticketNumber&&r.set(l.ticketNumber,l);localStorage.setItem("tickets",JSON.stringify(Array.from(r.values())))}catch(e){console.warn("No se pudo persistir tickets en localStorage:",e)}},he=async()=>{if(!u.value.length&&!v.value)return;const t=u.value.length?u.value:[v.value];let e=!1;t.length>1?e=await G.printMultipleTickets(t):e=await G.printThermalTicket(t[0]),e?(T.value="success",h.value="Impresión automática iniciada."):(T.value="error",h.value='No se pudo imprimir automáticamente. Usa el botón "Imprimir Entrada".')},ye=async()=>{var t,e,o,r,l,g,N,x,_,f,$;K.value=!0,R.value="",y.value=!1;try{const S=await $e.getPaymentOrderDetails(I.value)||{};if(!S.success||!S.order)throw new Error("Respuesta inválida del endpoint de detalle de orden");const c=S.order,M=Array.isArray(S.tickets)?S.tickets:[],C=(Array.isArray(S.payments)?S.payments:[])[0]||{},z=Number(c.total_amount??((t=C==null?void 0:C.response_data)==null?void 0:t.amount));if(!Number.isFinite(z))throw new Error("Monto no disponible desde backend");if(d.value={orderNumber:String(c.order_number||I.value),currency:String(c.currency||"ARS"),totalAmount:z,movieTitle:String(((o=(e=c.screening)==null?void 0:e.movie)==null?void 0:o.title)||"N/A"),cinemaName:String(((g=(l=(r=c.screening)==null?void 0:r.room)==null?void 0:l.cinema)==null?void 0:g.name)||"N/A"),roomName:String(((x=(N=c.screening)==null?void 0:N.room)==null?void 0:x.name)||"N/A"),format:String(((_=c.screening)==null?void 0:_.format)||"N/A"),screeningDate:Z((f=c.screening)==null?void 0:f.start_time),screeningTime:ee(($=c.screening)==null?void 0:$.start_time)},E.value={amount:z,status:C.status||c.status||"completed",paidAt:c.paid_at||C.completed_at||null,updatedAt:C.updated_at||c.updated_at||null,currency:String(c.currency||"ARS")},M.length===0){y.value=!0,R.value="Pago confirmado, pero las entradas aún no están disponibles. Continúa en ventanilla con tu número de orden.",v.value=null,u.value=[],T.value="error",h.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}const L=M.map(b=>ie(b,c));u.value=L,v.value=L[0],be(L),await he()}catch(U){console.error("Error al cargar datos del ticket:",U),R.value="Faltan datos del pago o el backend no respondió. Continúa en ventanilla.",y.value=!0,v.value=null,u.value=[],E.value=null,T.value="error",h.value="Impresión deshabilitada: ve a ventanilla con tu número de orden."}finally{K.value=!1}},Ne=async()=>{if(y.value){T.value="error",h.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}H.value=!0;try{const t=u.value.length?u.value:[v.value];(t.length>1?await G.printMultipleTickets(t):await G.printThermalTicket(t[0]))?(T.value="success",h.value="Impresión iniciada correctamente."):(T.value="error",h.value="No se pudo completar la impresión. Verifica tu impresora.")}catch(t){console.error("Error durante la impresión:",t),T.value="error",h.value="Error al intentar imprimir. Intenta de nuevo."}finally{H.value=!1}},xe=()=>{var U,S,c,M,Q,C,z,L;if(!V.value||y.value)return;const t=u.value.length?u.value:[v.value],e=String(J.value||"ARS").toUpperCase(),o={ARS:"es-AR",USD:"en-US",EUR:"es-ES"},r=new Intl.NumberFormat(o[e]||"es-AR",{style:"currency",currency:e,minimumFractionDigits:2}),l=t.map(b=>b==null?void 0:b.ticketNumber).filter(Boolean).join(", "),g=b=>{const se=Number(b);return Number.isFinite(se)?r.format(se):r.format(0)},N=t.map(b=>`
      <tr>
        <td style="padding: 12px 14px; border: 1px solid #dbe3ef;">${b.ticketNumber}</td>
        <td style="padding: 12px 14px; border: 1px solid #dbe3ef;">${b.seatNumber}</td>
        <td style="padding: 12px 14px; border: 1px solid #dbe3ef; text-align: right; font-variant-numeric: tabular-nums;">${g(b.price)}</td>
      </tr>
    `).join(""),x=`
    <!doctype html>
    <html lang="es">
      <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Entrada ${P.value}</title>
        <style>
          * { box-sizing: border-box; }
          body {
            margin: 0;
            background: #f3f6fb;
            color: #0f172a;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            line-height: 1.45;
            padding: 32px;
          }
          .doc {
            max-width: 840px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #d6deeb;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.08);
          }
          .header {
            background: #17336d;
            color: #ffffff;
            padding: 22px 26px;
            text-align: center;
          }
          .header h1 {
            margin: 0;
            font-size: 26px;
            letter-spacing: 0.3px;
          }
          .header p {
            margin: 8px 0 0;
            opacity: 0.95;
            font-size: 16px;
          }
          .content {
            padding: 24px 26px 28px;
          }
          .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
            font-size: 14px;
          }
          .info-card {
            background: #f8fafc;
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            padding: 12px;
          }
          .info-card.total {
            background: #ecfdf5;
            border-color: #86efac;
          }
          .label {
            color: #334155;
            font-weight: 600;
          }
          .value-strong {
            font-size: 20px;
            font-weight: 700;
            color: #166534;
            display: inline-block;
            margin-top: 4px;
          }
          table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
          }
          thead tr {
            background: #eef3fb;
          }
          th {
            padding: 12px 14px;
            border: 1px solid #dbe3ef;
            text-align: left;
            font-weight: 700;
            color: #1e293b;
          }
          th:last-child { text-align: right; }
          .footer-note {
            margin-top: 16px;
            color: #64748b;
            font-size: 12px;
            text-align: right;
          }
          @media print {
            body {
              background: #ffffff;
              padding: 10mm;
            }
            .doc {
              max-width: none;
              box-shadow: none;
              border: 1px solid #c9d4e5;
            }
            .content {
              padding: 18px 20px;
            }
            .info-grid {
              gap: 10px;
              margin-bottom: 14px;
            }
          }
        </style>
      </head>
      <body>
        <div class="doc">
          <div class="header">
            <h1>CINEA - Entradas</h1>
            <p><strong>${((U=d.value)==null?void 0:U.movieTitle)||((S=v.value)==null?void 0:S.movieTitle)||"Película"}</strong></p>
          </div>

          <div class="content">
            <div class="info-grid">
              <div class="info-card">
                <span class="label">Número de orden:</span><br>${P.value}
              </div>
              <div class="info-card">
                <span class="label">Número(s) de ticket:</span><br>${l||"N/A"}
              </div>
              <div class="info-card">
                <span class="label">Fecha:</span> ${((c=d.value)==null?void 0:c.screeningDate)||((M=v.value)==null?void 0:M.screeningDate)||"N/A"}<br>
                <span class="label">Hora:</span> ${((Q=d.value)==null?void 0:Q.screeningTime)||((C=v.value)==null?void 0:C.screeningTime)||"N/A"}
              </div>
              <div class="info-card total">
                <span class="label">Total:</span><br>
                <span class="value-strong">${g(((z=E.value)==null?void 0:z.amount)??((L=d.value)==null?void 0:L.totalAmount))}</span>
              </div>
            </div>

            <table>
              <thead>
                <tr>
                  <th>Ticket</th>
                  <th>Asiento(s)</th>
                  <th>Precio</th>
                </tr>
              </thead>
              <tbody>
                ${N}
              </tbody>
            </table>
            <p class="footer-note">Moneda: ${e}</p>
          </div>
        </div>
      </body>
    </html>
  `,_=new Blob([x],{type:"text/html"}),f=window.URL.createObjectURL(_),$=document.createElement("a");$.href=f,$.download=`entrada_${P.value}.html`,$.click(),window.URL.revokeObjectURL(f)};return(t,e)=>{var r,l,g,N,x,_;const o=ke("router-link");return A(),k("div",ze,[a("div",Le,[a("div",Fe,[e[31]||(e[31]=a("div",{class:"success-header"},[a("div",{class:"success-icon-large icon-success"}),a("h1",null,"¡Pago Exitoso!"),a("p",{class:"success-subtitle"},"Tu compra ha sido procesada correctamente")],-1)),K.value?(A(),k("p",Oe,"Cargando información de la compra...")):R.value?(A(),k("p",Ue,n(R.value),1)):Y("",!0),y.value?(A(),k("div",Me,[e[1]||(e[1]=a("h3",null,"Atención en ventanilla requerida",-1)),e[2]||(e[2]=a("p",null,"No pudimos validar todos los datos del pago en línea.",-1)),a("p",null,[e[0]||(e[0]=a("strong",null,"Orden:",-1)),B(" "+n(P.value),1)]),e[3]||(e[3]=a("p",null,"Acércate a ventanilla con este número para finalizar la entrega de entradas.",-1))])):Y("",!0),V.value&&!y.value?(A(),k("div",Be,[a("div",He,[e[12]||(e[12]=a("h3",null,"Datos de la Orden",-1)),a("div",je,[a("div",qe,[e[4]||(e[4]=a("span",{class:"info-label"},"Película:",-1)),a("span",Ve,n(((r=d.value)==null?void 0:r.movieTitle)||"N/A"),1)]),a("div",Je,[e[5]||(e[5]=a("span",{class:"info-label"},"Cine:",-1)),a("span",We,n(((l=d.value)==null?void 0:l.cinemaName)||"N/A"),1)]),a("div",Ye,[e[6]||(e[6]=a("span",{class:"info-label"},"Sala:",-1)),a("span",Ge,n(((g=d.value)==null?void 0:g.roomName)||"N/A"),1)]),a("div",Ke,[e[7]||(e[7]=a("span",{class:"info-label"},"Formato:",-1)),a("span",Qe,n(((N=d.value)==null?void 0:N.format)||"N/A"),1)]),a("div",Xe,[e[8]||(e[8]=a("span",{class:"info-label"},"Fecha:",-1)),a("span",Ze,n(((x=d.value)==null?void 0:x.screeningDate)||"N/A"),1)]),a("div",et,[e[9]||(e[9]=a("span",{class:"info-label"},"Hora:",-1)),a("span",tt,n(((_=d.value)==null?void 0:_.screeningTime)||"N/A"),1)]),a("div",at,[e[10]||(e[10]=a("span",{class:"info-label"},"Entradas:",-1)),a("span",st,n(ce.value),1)]),a("div",nt,[e[11]||(e[11]=a("span",{class:"info-label"},"Total:",-1)),a("span",ot,n(te.value)+" "+n(J.value),1)])])]),a("div",rt,[e[17]||(e[17]=a("h3",null,"Entradas",-1)),a("div",it,[(A(!0),k(Te,null,Ce(u.value,f=>(A(),k("div",{key:f.ticketNumber,class:"ticket-item"},[a("div",lt,[e[13]||(e[13]=a("span",{class:"info-label"},"Número:",-1)),a("span",dt,n(f.ticketNumber),1)]),a("div",ct,[e[14]||(e[14]=a("span",{class:"info-label"},"Asiento(s):",-1)),a("span",ut,n(f.seatNumber),1)]),a("div",mt,[e[15]||(e[15]=a("span",{class:"info-label"},"Precio:",-1)),a("span",pt,n(f.price)+" "+n(J.value),1)]),a("div",vt,[e[16]||(e[16]=a("span",{class:"info-label"},"Estado:",-1)),a("span",gt,n(f.statusText),1)])]))),128))])])])):Y("",!0),a("div",ft,[e[23]||(e[23]=a("h3",null,"Detalles de la Compra",-1)),a("div",bt,[e[18]||(e[18]=a("span",null,"Número de Orden:",-1)),a("span",ht,n(P.value),1)]),a("div",yt,[e[19]||(e[19]=a("span",null,"Código de Compra:",-1)),a("span",Nt,n(P.value),1)]),a("div",xt,[e[20]||(e[20]=a("span",null,"Fecha de Compra:",-1)),a("span",null,n(de.value),1)]),a("div",_t,[e[21]||(e[21]=a("span",null,"Importe Pagado:",-1)),a("span",St,n(te.value)+" "+n(J.value),1)]),a("div",wt,[e[22]||(e[22]=a("span",null,"Estado:",-1)),a("span",At,n(pe.value),1)])]),a("div",kt,[e[29]||(e[29]=a("h3",null,"Próximos Pasos",-1)),a("ol",null,[a("li",null,[e[24]||(e[24]=B("Recuerda tu número de orden: ",-1)),a("strong",null,n(P.value),1)]),e[25]||(e[25]=a("li",null,"Recibirás un email de confirmación con tu comprobante",-1)),e[26]||(e[26]=a("li",null,"Puedes imprimir tu entrada desde el botón de abajo",-1)),e[27]||(e[27]=a("li",null,"Presenta tu entrada en la taquilla del cine",-1)),e[28]||(e[28]=a("li",null,"Llega 15 minutos antes de que comience la función",-1))]),a("p",Tt," Esta pantalla se cerrará automáticamente en "+n(ve.value)+" y te llevaremos al inicio. ",1)]),a("div",Ct,[a("button",{onClick:Ne,class:"btn btn-primary btn-large",disabled:H.value||y.value||!V.value},[H.value?(A(),k("span",It,"Imprimiendo...")):(A(),k("span",Et,n(ue.value),1))],8,Dt),a("button",{onClick:xe,class:"btn btn-secondary btn-large",disabled:y.value||!V.value},n(me.value),9,Pt),De(o,{to:"/",class:"btn btn-tertiary btn-large"},{default:Ee(()=>[...e[30]||(e[30]=[B(" Volver al Inicio ",-1)])]),_:1})]),h.value?(A(),k("p",{key:4,class:Ie(["print-message",T.value])},n(h.value),3)):Y("",!0),e[32]||(e[32]=a("div",{class:"contact-section"},[a("h4",null,"¿Necesitas ayuda?"),a("p",null,[B("Contacta con nosotros en "),a("strong",null,"info@cinea.es")]),a("p",null,[B("Teléfono: "),a("strong",null,"+34 91 123 4567")]),a("p",{class:"hours"},"Lun-Dom: 10:00 - 22:00")],-1))])]),e[33]||(e[33]=a("div",{id:"print-frame",style:{display:"none"}},null,-1))])}}},Lt=Pe(Rt,[["__scopeId","data-v-b83fd7a9"]]);export{Lt as default};
