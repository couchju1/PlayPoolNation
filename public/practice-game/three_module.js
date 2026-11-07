"use strict";(self.webpackChunkbilliards=self.webpackChunkbilliards||[]).push([[668],{"./node_modules/three/build/three.module.js"(e,t,i){i.d(t,{JeP:()=>WebGLRenderer});var r=i("./node_modules/three/build/three.core.js");/**
 * @license
 * Copyright 2010-2026 Three.js Authors
 * SPDX-License-Identifier: MIT
 */function a(){let e=null,t=!1,i=null,r=null;function a(t,n){r=e.requestAnimationFrame(a),i(t,n)}return{start:function(){!0===t||null===i||null!==e&&(r=e.requestAnimationFrame(a),t=!0)},stop:function(){null!==e&&e.cancelAnimationFrame(r),t=!1},setAnimationLoop:function(e){i=e},setContext:function(t){e=t}}}function n(e){let t=new WeakMap;return{get:function(e){return e.isInterleavedBufferAttribute&&(e=e.data),t.get(e)},remove:function(i){i.isInterleavedBufferAttribute&&(i=i.data);let r=t.get(i);r&&(e.deleteBuffer(r.buffer),t.delete(i))},update:function(i,r){if(i.isInterleavedBufferAttribute&&(i=i.data),i.isGLBufferAttribute){let e=t.get(i);(!e||e.version<i.version)&&t.set(i,{buffer:i.buffer,type:i.type,bytesPerElement:i.elementSize,version:i.version});return}let a=t.get(i);if(void 0===a)t.set(i,function(t,i){let r,a=t.array,n=t.usage,o=a.byteLength,l=e.createBuffer();if(e.bindBuffer(i,l),e.bufferData(i,a,n),t.onUploadCallback(),a instanceof Float32Array)r=e.FLOAT;else if("u">typeof Float16Array&&a instanceof Float16Array)r=e.HALF_FLOAT;else if(a instanceof Uint16Array)r=t.isFloat16BufferAttribute?e.HALF_FLOAT:e.UNSIGNED_SHORT;else if(a instanceof Int16Array)r=e.SHORT;else if(a instanceof Uint32Array)r=e.UNSIGNED_INT;else if(a instanceof Int32Array)r=e.INT;else if(a instanceof Int8Array)r=e.BYTE;else if(a instanceof Uint8Array)r=e.UNSIGNED_BYTE;else if(a instanceof Uint8ClampedArray)r=e.UNSIGNED_BYTE;else throw Error("THREE.WebGLAttributes: Unsupported buffer data format: "+a);return{buffer:l,type:r,bytesPerElement:a.BYTES_PER_ELEMENT,version:t.version,size:o}}(i,r));else if(a.version<i.version){if(a.size!==i.array.byteLength)throw Error("THREE.WebGLAttributes: The size of the buffer attribute's array buffer does not match the original size. Resizing buffer attributes is not supported.");!function(t,i,r){let a=i.array,n=i.updateRanges;if(e.bindBuffer(r,t),0===n.length)e.bufferSubData(r,0,a);else{n.sort((e,t)=>e.start-t.start);let t=0;for(let e=1;e<n.length;e++){let i=n[t],r=n[e];r.start<=i.start+i.count+1?i.count=Math.max(i.count,r.start+r.count-i.start):n[++t]=r}n.length=t+1;for(let t=0,i=n.length;t<i;t++){let i=n[t];e.bufferSubData(r,i.start*a.BYTES_PER_ELEMENT,a,i.start,i.count)}i.clearUpdateRanges()}i.onUploadCallback()}(a.buffer,i,r),a.version=i.version}}}}let o={alphahash_fragment:`#ifdef USE_ALPHAHASH
	if ( diffuseColor.a < getAlphaHashThreshold( vPosition ) ) discard;
#endif`,alphahash_pars_fragment:`#ifdef USE_ALPHAHASH
	const float ALPHA_HASH_SCALE = 0.05;
	float hash2D( vec2 value ) {
		return fract( 1.0e4 * sin( 17.0 * value.x + 0.1 * value.y ) * ( 0.1 + abs( sin( 13.0 * value.y + value.x ) ) ) );
	}
	float hash3D( vec3 value ) {
		return hash2D( vec2( hash2D( value.xy ), value.z ) );
	}
	float getAlphaHashThreshold( vec3 position ) {
		float maxDeriv = max(
			length( dFdx( position.xyz ) ),
			length( dFdy( position.xyz ) )
		);
		float pixScale = 1.0 / ( ALPHA_HASH_SCALE * maxDeriv );
		vec2 pixScales = vec2(
			exp2( floor( log2( pixScale ) ) ),
			exp2( ceil( log2( pixScale ) ) )
		);
		vec2 alpha = vec2(
			hash3D( floor( pixScales.x * position.xyz ) ),
			hash3D( floor( pixScales.y * position.xyz ) )
		);
		float lerpFactor = fract( log2( pixScale ) );
		float x = ( 1.0 - lerpFactor ) * alpha.x + lerpFactor * alpha.y;
		float a = min( lerpFactor, 1.0 - lerpFactor );
		vec3 cases = vec3(
			x * x / ( 2.0 * a * ( 1.0 - a ) ),
			( x - 0.5 * a ) / ( 1.0 - a ),
			1.0 - ( ( 1.0 - x ) * ( 1.0 - x ) / ( 2.0 * a * ( 1.0 - a ) ) )
		);
		float threshold = ( x < ( 1.0 - a ) )
			? ( ( x < a ) ? cases.x : cases.y )
			: cases.z;
		return clamp( threshold , 1.0e-6, 1.0 );
	}
#endif`,alphamap_fragment:`#ifdef USE_ALPHAMAP
	diffuseColor.a *= texture2D( alphaMap, vAlphaMapUv ).g;
#endif`,alphamap_pars_fragment:`#ifdef USE_ALPHAMAP
	uniform sampler2D alphaMap;
#endif`,alphatest_fragment:`#ifdef USE_ALPHATEST
	#ifdef ALPHA_TO_COVERAGE
	diffuseColor.a = smoothstep( alphaTest, alphaTest + fwidth( diffuseColor.a ), diffuseColor.a );
	if ( diffuseColor.a == 0.0 ) discard;
	#else
	if ( diffuseColor.a < alphaTest ) discard;
	#endif
#endif`,alphatest_pars_fragment:`#ifdef USE_ALPHATEST
	uniform float alphaTest;
#endif`,aomap_fragment:`#ifdef USE_AOMAP
	float ambientOcclusion = ( texture2D( aoMap, vAoMapUv ).r - 1.0 ) * aoMapIntensity + 1.0;
	reflectedLight.indirectDiffuse *= ambientOcclusion;
	#if defined( USE_CLEARCOAT ) 
		clearcoatSpecularIndirect *= ambientOcclusion;
	#endif
	#if defined( USE_SHEEN ) 
		sheenSpecularIndirect *= ambientOcclusion;
	#endif
	#if defined( USE_ENVMAP ) && defined( STANDARD )
		float dotNV = saturate( dot( geometryNormal, geometryViewDir ) );
		reflectedLight.indirectSpecular *= computeSpecularOcclusion( dotNV, ambientOcclusion, material.roughness );
	#endif
#endif`,aomap_pars_fragment:`#ifdef USE_AOMAP
	uniform sampler2D aoMap;
	uniform float aoMapIntensity;
#endif`,batching_pars_vertex:`#ifdef USE_BATCHING
	#if ! defined( GL_ANGLE_multi_draw )
	#define gl_DrawID _gl_DrawID
	uniform int _gl_DrawID;
	#endif
	uniform highp sampler2D batchingTexture;
	uniform highp usampler2D batchingIdTexture;
	mat4 getBatchingMatrix( const in float i ) {
		int size = textureSize( batchingTexture, 0 ).x;
		int j = int( i ) * 4;
		int x = j % size;
		int y = j / size;
		vec4 v1 = texelFetch( batchingTexture, ivec2( x, y ), 0 );
		vec4 v2 = texelFetch( batchingTexture, ivec2( x + 1, y ), 0 );
		vec4 v3 = texelFetch( batchingTexture, ivec2( x + 2, y ), 0 );
		vec4 v4 = texelFetch( batchingTexture, ivec2( x + 3, y ), 0 );
		return mat4( v1, v2, v3, v4 );
	}
	float getIndirectIndex( const in int i ) {
		int size = textureSize( batchingIdTexture, 0 ).x;
		int x = i % size;
		int y = i / size;
		return float( texelFetch( batchingIdTexture, ivec2( x, y ), 0 ).r );
	}
#endif
#ifdef USE_BATCHING_COLOR
	uniform sampler2D batchingColorTexture;
	vec4 getBatchingColor( const in float i ) {
		int size = textureSize( batchingColorTexture, 0 ).x;
		int j = int( i );
		int x = j % size;
		int y = j / size;
		return texelFetch( batchingColorTexture, ivec2( x, y ), 0 );
	}
#endif`,batching_vertex:`#ifdef USE_BATCHING
	mat4 batchingMatrix = getBatchingMatrix( getIndirectIndex( gl_DrawID ) );
#endif`,begin_vertex:`vec3 transformed = vec3( position );
#ifdef USE_ALPHAHASH
	vPosition = vec3( position );
#endif`,beginnormal_vertex:`vec3 objectNormal = vec3( normal );
#ifdef USE_TANGENT
	vec3 objectTangent = vec3( tangent.xyz );
#endif`,bsdfs:`float G_BlinnPhong_Implicit( ) {
	return 0.25;
}
float D_BlinnPhong( const in float shininess, const in float dotNH ) {
	return RECIPROCAL_PI * ( shininess * 0.5 + 1.0 ) * pow( dotNH, shininess );
}
vec3 BRDF_BlinnPhong( const in vec3 lightDir, const in vec3 viewDir, const in vec3 normal, const in vec3 specularColor, const in float shininess ) {
	vec3 halfDir = normalize( lightDir + viewDir );
	float dotNH = saturate( dot( normal, halfDir ) );
	float dotVH = saturate( dot( viewDir, halfDir ) );
	vec3 F = F_Schlick( specularColor, 1.0, dotVH );
	float G = G_BlinnPhong_Implicit( );
	float D = D_BlinnPhong( shininess, dotNH );
	return F * ( G * D );
} // validated`,iridescence_fragment:`#ifdef USE_IRIDESCENCE
	const mat3 XYZ_TO_REC709 = mat3(
		 3.2404542, -0.9692660,  0.0556434,
		-1.5371385,  1.8760108, -0.2040259,
		-0.4985314,  0.0415560,  1.0572252
	);
	vec3 Fresnel0ToIor( vec3 fresnel0 ) {
		vec3 sqrtF0 = sqrt( fresnel0 );
		return ( vec3( 1.0 ) + sqrtF0 ) / ( vec3( 1.0 ) - sqrtF0 );
	}
	vec3 IorToFresnel0( vec3 transmittedIor, float incidentIor ) {
		return pow2( ( transmittedIor - vec3( incidentIor ) ) / ( transmittedIor + vec3( incidentIor ) ) );
	}
	float IorToFresnel0( float transmittedIor, float incidentIor ) {
		return pow2( ( transmittedIor - incidentIor ) / ( transmittedIor + incidentIor ));
	}
	vec3 evalSensitivity( float OPD, vec3 shift ) {
		float phase = 2.0 * PI * OPD * 1.0e-9;
		vec3 val = vec3( 5.4856e-13, 4.4201e-13, 5.2481e-13 );
		vec3 pos = vec3( 1.6810e+06, 1.7953e+06, 2.2084e+06 );
		vec3 var = vec3( 4.3278e+09, 9.3046e+09, 6.6121e+09 );
		vec3 xyz = val * sqrt( 2.0 * PI * var ) * cos( pos * phase + shift ) * exp( - pow2( phase ) * var );
		xyz.x += 9.7470e-14 * sqrt( 2.0 * PI * 4.5282e+09 ) * cos( 2.2399e+06 * phase + shift[ 0 ] ) * exp( - 4.5282e+09 * pow2( phase ) );
		xyz /= 1.0685e-7;
		vec3 rgb = XYZ_TO_REC709 * xyz;
		return rgb;
	}
	vec3 evalIridescence( float outsideIOR, float eta2, float cosTheta1, float thinFilmThickness, vec3 baseF0 ) {
		vec3 I;
		float iridescenceIOR = mix( outsideIOR, eta2, smoothstep( 0.0, 0.03, thinFilmThickness ) );
		float sinTheta2Sq = pow2( outsideIOR / iridescenceIOR ) * ( 1.0 - pow2( cosTheta1 ) );
		float cosTheta2Sq = 1.0 - sinTheta2Sq;
		if ( cosTheta2Sq < 0.0 ) {
			return vec3( 1.0 );
		}
		float cosTheta2 = sqrt( cosTheta2Sq );
		float R0 = IorToFresnel0( iridescenceIOR, outsideIOR );
		float R12 = F_Schlick( R0, 1.0, cosTheta1 );
		float T121 = 1.0 - R12;
		float phi12 = 0.0;
		if ( iridescenceIOR < outsideIOR ) phi12 = PI;
		float phi21 = PI - phi12;
		vec3 baseIOR = Fresnel0ToIor( clamp( baseF0, 0.0, 0.9999 ) );		vec3 R1 = IorToFresnel0( baseIOR, iridescenceIOR );
		vec3 R23 = F_Schlick( R1, 1.0, cosTheta2 );
		vec3 phi23 = vec3( 0.0 );
		if ( baseIOR[ 0 ] < iridescenceIOR ) phi23[ 0 ] = PI;
		if ( baseIOR[ 1 ] < iridescenceIOR ) phi23[ 1 ] = PI;
		if ( baseIOR[ 2 ] < iridescenceIOR ) phi23[ 2 ] = PI;
		float OPD = 2.0 * iridescenceIOR * thinFilmThickness * cosTheta2;
		vec3 phi = vec3( phi21 ) + phi23;
		vec3 R123 = clamp( R12 * R23, 1e-5, 0.9999 );
		vec3 r123 = sqrt( R123 );
		vec3 Rs = pow2( T121 ) * R23 / ( vec3( 1.0 ) - R123 );
		vec3 C0 = R12 + Rs;
		I = C0;
		vec3 Cm = Rs - T121;
		for ( int m = 1; m <= 2; ++ m ) {
			Cm *= r123;
			vec3 Sm = 2.0 * evalSensitivity( float( m ) * OPD, float( m ) * phi );
			I += Cm * Sm;
		}
		return max( I, vec3( 0.0 ) );
	}
#endif`,bumpmap_pars_fragment:`#ifdef USE_BUMPMAP
	uniform sampler2D bumpMap;
	uniform float bumpScale;
	vec2 dHdxy_fwd() {
		vec2 dSTdx = dFdx( vBumpMapUv );
		vec2 dSTdy = dFdy( vBumpMapUv );
		float Hll = bumpScale * texture2D( bumpMap, vBumpMapUv ).x;
		float dBx = bumpScale * texture2D( bumpMap, vBumpMapUv + dSTdx ).x - Hll;
		float dBy = bumpScale * texture2D( bumpMap, vBumpMapUv + dSTdy ).x - Hll;
		return vec2( dBx, dBy );
	}
	vec3 perturbNormalArb( vec3 surf_pos, vec3 surf_norm, vec2 dHdxy, float faceDirection ) {
		vec3 vSigmaX = normalize( dFdx( surf_pos.xyz ) );
		vec3 vSigmaY = normalize( dFdy( surf_pos.xyz ) );
		vec3 vN = surf_norm;
		vec3 R1 = cross( vSigmaY, vN );
		vec3 R2 = cross( vN, vSigmaX );
		float fDet = dot( vSigmaX, R1 ) * faceDirection;
		vec3 vGrad = sign( fDet ) * ( dHdxy.x * R1 + dHdxy.y * R2 );
		return normalize( abs( fDet ) * surf_norm - vGrad );
	}
#endif`,clipping_planes_fragment:`#if NUM_CLIPPING_PLANES > 0
	vec4 plane;
	#ifdef ALPHA_TO_COVERAGE
		float distanceToPlane, distanceGradient;
		float clipOpacity = 1.0;
		#pragma unroll_loop_start
		for ( int i = 0; i < UNION_CLIPPING_PLANES; i ++ ) {
			plane = clippingPlanes[ i ];
			distanceToPlane = - dot( vClipPosition, plane.xyz ) + plane.w;
			distanceGradient = fwidth( distanceToPlane ) / 2.0;
			clipOpacity *= smoothstep( - distanceGradient, distanceGradient, distanceToPlane );
			if ( clipOpacity == 0.0 ) discard;
		}
		#pragma unroll_loop_end
		#if UNION_CLIPPING_PLANES < NUM_CLIPPING_PLANES
			float unionClipOpacity = 1.0;
			#pragma unroll_loop_start
			for ( int i = UNION_CLIPPING_PLANES; i < NUM_CLIPPING_PLANES; i ++ ) {
				plane = clippingPlanes[ i ];
				distanceToPlane = - dot( vClipPosition, plane.xyz ) + plane.w;
				distanceGradient = fwidth( distanceToPlane ) / 2.0;
				unionClipOpacity *= 1.0 - smoothstep( - distanceGradient, distanceGradient, distanceToPlane );
			}
			#pragma unroll_loop_end
			clipOpacity *= 1.0 - unionClipOpacity;
		#endif
		diffuseColor.a *= clipOpacity;
		if ( diffuseColor.a == 0.0 ) discard;
	#else
		#pragma unroll_loop_start
		for ( int i = 0; i < UNION_CLIPPING_PLANES; i ++ ) {
			plane = clippingPlanes[ i ];
			if ( dot( vClipPosition, plane.xyz ) > plane.w ) discard;
		}
		#pragma unroll_loop_end
		#if UNION_CLIPPING_PLANES < NUM_CLIPPING_PLANES
			bool clipped = true;
			#pragma unroll_loop_start
			for ( int i = UNION_CLIPPING_PLANES; i < NUM_CLIPPING_PLANES; i ++ ) {
				plane = clippingPlanes[ i ];
				clipped = ( dot( vClipPosition, plane.xyz ) > plane.w ) && clipped;
			}
			#pragma unroll_loop_end
			if ( clipped ) discard;
		#endif
	#endif
#endif`,clipping_planes_pars_fragment:`#if NUM_CLIPPING_PLANES > 0
	varying vec3 vClipPosition;
	uniform vec4 clippingPlanes[ NUM_CLIPPING_PLANES ];
#endif`,clipping_planes_pars_vertex:`#if NUM_CLIPPING_PLANES > 0
	varying vec3 vClipPosition;
#endif`,clipping_planes_vertex:`#if NUM_CLIPPING_PLANES > 0
	vClipPosition = - mvPosition.xyz;
#endif`,color_fragment:`#if defined( USE_COLOR ) || defined( USE_COLOR_ALPHA )
	diffuseColor *= vColor;
#endif`,color_pars_fragment:`#if defined( USE_COLOR ) || defined( USE_COLOR_ALPHA )
	varying vec4 vColor;
#endif`,color_pars_vertex:`#if defined( USE_COLOR ) || defined( USE_COLOR_ALPHA ) || defined( USE_INSTANCING_COLOR ) || defined( USE_BATCHING_COLOR )
	varying vec4 vColor;
#endif`,color_vertex:`#if defined( USE_COLOR ) || defined( USE_COLOR_ALPHA ) || defined( USE_INSTANCING_COLOR ) || defined( USE_BATCHING_COLOR )
	vColor = vec4( 1.0 );
#endif
#ifdef USE_COLOR_ALPHA
	vColor *= color;
#elif defined( USE_COLOR )
	vColor.rgb *= color;
#endif
#ifdef USE_INSTANCING_COLOR
	vColor.rgb *= instanceColor.rgb;
#endif
#ifdef USE_BATCHING_COLOR
	vColor *= getBatchingColor( getIndirectIndex( gl_DrawID ) );
#endif`,common:`#define PI 3.141592653589793
#define PI2 6.283185307179586
#define PI_HALF 1.5707963267948966
#define RECIPROCAL_PI 0.3183098861837907
#define RECIPROCAL_PI2 0.15915494309189535
#define EPSILON 1e-6
#ifndef saturate
#define saturate( a ) clamp( a, 0.0, 1.0 )
#endif
#define whiteComplement( a ) ( 1.0 - saturate( a ) )
float pow2( const in float x ) { return x*x; }
vec3 pow2( const in vec3 x ) { return x*x; }
float pow3( const in float x ) { return x*x*x; }
float pow4( const in float x ) { float x2 = x*x; return x2*x2; }
float max3( const in vec3 v ) { return max( max( v.x, v.y ), v.z ); }
float average( const in vec3 v ) { return dot( v, vec3( 0.3333333 ) ); }
highp float rand( const in vec2 uv ) {
	const highp float a = 12.9898, b = 78.233, c = 43758.5453;
	highp float dt = dot( uv.xy, vec2( a,b ) ), sn = mod( dt, PI );
	return fract( sin( sn ) * c );
}
#ifdef HIGH_PRECISION
	float precisionSafeLength( vec3 v ) { return length( v ); }
#else
	float precisionSafeLength( vec3 v ) {
		float maxComponent = max3( abs( v ) );
		return length( v / maxComponent ) * maxComponent;
	}
#endif
struct IncidentLight {
	vec3 color;
	vec3 direction;
	bool visible;
};
struct ReflectedLight {
	vec3 directDiffuse;
	vec3 directSpecular;
	vec3 indirectDiffuse;
	vec3 indirectSpecular;
};
#ifdef USE_ALPHAHASH
	varying vec3 vPosition;
#endif
vec3 transformDirection( in vec3 dir, in mat4 matrix ) {
	return normalize( ( matrix * vec4( dir, 0.0 ) ).xyz );
}
#define inverseTransformDirection transformDirectionByInverseViewMatrix
vec3 transformNormalByInverseViewMatrix( in vec3 normal, in mat4 viewMatrix ) {
	return normalize( ( vec4( normal, 0.0 ) * viewMatrix ).xyz );
}
vec3 transformDirectionByInverseViewMatrix( in vec3 dir, in mat4 viewMatrix ) {
	return normalize( ( vec4( dir, 0.0 ) * viewMatrix ).xyz );
}
bool isPerspectiveMatrix( mat4 m ) {
	return m[ 2 ][ 3 ] == - 1.0;
}
vec2 equirectUv( in vec3 dir ) {
	float u = atan( dir.z, dir.x ) * RECIPROCAL_PI2 + 0.5;
	float v = asin( clamp( dir.y, - 1.0, 1.0 ) ) * RECIPROCAL_PI + 0.5;
	return vec2( u, v );
}
vec3 BRDF_Lambert( const in vec3 diffuseColor ) {
	return RECIPROCAL_PI * diffuseColor;
}
vec3 F_Schlick( const in vec3 f0, const in float f90, const in float dotVH ) {
	float fresnel = exp2( ( - 5.55473 * dotVH - 6.98316 ) * dotVH );
	return f0 * ( 1.0 - fresnel ) + ( f90 * fresnel );
}
float F_Schlick( const in float f0, const in float f90, const in float dotVH ) {
	float fresnel = exp2( ( - 5.55473 * dotVH - 6.98316 ) * dotVH );
	return f0 * ( 1.0 - fresnel ) + ( f90 * fresnel );
} // validated`,cube_uv_reflection_fragment:`#ifdef ENVMAP_TYPE_CUBE_UV
	#define cubeUV_minMipLevel 4.0
	#define cubeUV_minTileSize 16.0
	float getFace( vec3 direction ) {
		vec3 absDirection = abs( direction );
		float face = - 1.0;
		if ( absDirection.x > absDirection.z ) {
			if ( absDirection.x > absDirection.y )
				face = direction.x > 0.0 ? 0.0 : 3.0;
			else
				face = direction.y > 0.0 ? 1.0 : 4.0;
		} else {
			if ( absDirection.z > absDirection.y )
				face = direction.z > 0.0 ? 2.0 : 5.0;
			else
				face = direction.y > 0.0 ? 1.0 : 4.0;
		}
		return face;
	}
	vec2 getUV( vec3 direction, float face ) {
		vec2 uv;
		if ( face == 0.0 ) {
			uv = vec2( direction.z, direction.y ) / abs( direction.x );
		} else if ( face == 1.0 ) {
			uv = vec2( - direction.x, - direction.z ) / abs( direction.y );
		} else if ( face == 2.0 ) {
			uv = vec2( - direction.x, direction.y ) / abs( direction.z );
		} else if ( face == 3.0 ) {
			uv = vec2( - direction.z, direction.y ) / abs( direction.x );
		} else if ( face == 4.0 ) {
			uv = vec2( - direction.x, direction.z ) / abs( direction.y );
		} else {
			uv = vec2( direction.x, direction.y ) / abs( direction.z );
		}
		return 0.5 * ( uv + 1.0 );
	}
	vec3 bilinearCubeUV( sampler2D envMap, vec3 direction, float mipInt ) {
		float face = getFace( direction );
		float filterInt = max( cubeUV_minMipLevel - mipInt, 0.0 );
		mipInt = max( mipInt, cubeUV_minMipLevel );
		float faceSize = exp2( mipInt );
		highp vec2 uv = getUV( direction, face ) * ( faceSize - 2.0 ) + 1.0;
		if ( face > 2.0 ) {
			uv.y += faceSize;
			face -= 3.0;
		}
		uv.x += face * faceSize;
		uv.x += filterInt * 3.0 * cubeUV_minTileSize;
		uv.y += 4.0 * ( exp2( CUBEUV_MAX_MIP ) - faceSize );
		uv.x *= CUBEUV_TEXEL_WIDTH;
		uv.y *= CUBEUV_TEXEL_HEIGHT;
		#ifdef texture2DGradEXT
			return texture2DGradEXT( envMap, uv, vec2( 0.0 ), vec2( 0.0 ) ).rgb;
		#else
			return texture2D( envMap, uv ).rgb;
		#endif
	}
	#define cubeUV_r0 1.0
	#define cubeUV_m0 - 2.0
	#define cubeUV_r1 0.8
	#define cubeUV_m1 - 1.0
	#define cubeUV_r4 0.4
	#define cubeUV_m4 2.0
	#define cubeUV_r5 0.305
	#define cubeUV_m5 3.0
	#define cubeUV_r6 0.21
	#define cubeUV_m6 4.0
	float roughnessToMip( float roughness ) {
		float mip = 0.0;
		if ( roughness >= cubeUV_r1 ) {
			mip = ( cubeUV_r0 - roughness ) * ( cubeUV_m1 - cubeUV_m0 ) / ( cubeUV_r0 - cubeUV_r1 ) + cubeUV_m0;
		} else if ( roughness >= cubeUV_r4 ) {
			mip = ( cubeUV_r1 - roughness ) * ( cubeUV_m4 - cubeUV_m1 ) / ( cubeUV_r1 - cubeUV_r4 ) + cubeUV_m1;
		} else if ( roughness >= cubeUV_r5 ) {
			mip = ( cubeUV_r4 - roughness ) * ( cubeUV_m5 - cubeUV_m4 ) / ( cubeUV_r4 - cubeUV_r5 ) + cubeUV_m4;
		} else if ( roughness >= cubeUV_r6 ) {
			mip = ( cubeUV_r5 - roughness ) * ( cubeUV_m6 - cubeUV_m5 ) / ( cubeUV_r5 - cubeUV_r6 ) + cubeUV_m5;
		} else {
			mip = - 2.0 * log2( 1.16 * roughness );		}
		return mip;
	}
	vec4 textureCubeUV( sampler2D envMap, vec3 sampleDir, float roughness ) {
		float mip = clamp( roughnessToMip( roughness ), cubeUV_m0, CUBEUV_MAX_MIP );
		float mipF = fract( mip );
		float mipInt = floor( mip );
		vec3 color0 = bilinearCubeUV( envMap, sampleDir, mipInt );
		if ( mipF == 0.0 ) {
			return vec4( color0, 1.0 );
		} else {
			vec3 color1 = bilinearCubeUV( envMap, sampleDir, mipInt + 1.0 );
			return vec4( mix( color0, color1, mipF ), 1.0 );
		}
	}
#endif`,defaultnormal_vertex:`vec3 transformedNormal = objectNormal;
#ifdef USE_TANGENT
	vec3 transformedTangent = objectTangent;
#endif
#ifdef USE_BATCHING
	mat3 bm = mat3( batchingMatrix );
	transformedNormal /= vec3( dot( bm[ 0 ], bm[ 0 ] ), dot( bm[ 1 ], bm[ 1 ] ), dot( bm[ 2 ], bm[ 2 ] ) );
	transformedNormal = bm * transformedNormal;
	#ifdef USE_TANGENT
		transformedTangent = bm * transformedTangent;
	#endif
#endif
#ifdef USE_INSTANCING
	mat3 im = mat3( instanceMatrix );
	transformedNormal /= vec3( dot( im[ 0 ], im[ 0 ] ), dot( im[ 1 ], im[ 1 ] ), dot( im[ 2 ], im[ 2 ] ) );
	transformedNormal = im * transformedNormal;
	#ifdef USE_TANGENT
		transformedTangent = im * transformedTangent;
	#endif
#endif
transformedNormal = normalMatrix * transformedNormal;
#ifdef FLIP_SIDED
	transformedNormal = - transformedNormal;
#endif
#ifdef USE_TANGENT
	transformedTangent = ( modelViewMatrix * vec4( transformedTangent, 0.0 ) ).xyz;
#endif`,displacementmap_pars_vertex:`#ifdef USE_DISPLACEMENTMAP
	uniform sampler2D displacementMap;
	uniform float displacementScale;
	uniform float displacementBias;
#endif`,displacementmap_vertex:`#ifdef USE_DISPLACEMENTMAP
	transformed += normalize( objectNormal ) * ( texture2D( displacementMap, vDisplacementMapUv ).x * displacementScale + displacementBias );
#endif`,emissivemap_fragment:`#ifdef USE_EMISSIVEMAP
	vec4 emissiveColor = texture2D( emissiveMap, vEmissiveMapUv );
	#ifdef DECODE_VIDEO_TEXTURE_EMISSIVE
		emissiveColor = sRGBTransferEOTF( emissiveColor );
	#endif
	totalEmissiveRadiance *= emissiveColor.rgb;
#endif`,emissivemap_pars_fragment:`#ifdef USE_EMISSIVEMAP
	uniform sampler2D emissiveMap;
#endif`,colorspace_fragment:"gl_FragColor = linearToOutputTexel( gl_FragColor );",colorspace_pars_fragment:`vec4 LinearTransferOETF( in vec4 value ) {
	return value;
}
vec4 sRGBTransferEOTF( in vec4 value ) {
	return vec4( mix( pow( value.rgb * 0.9478672986 + vec3( 0.0521327014 ), vec3( 2.4 ) ), value.rgb * 0.0773993808, vec3( lessThanEqual( value.rgb, vec3( 0.04045 ) ) ) ), value.a );
}
vec4 sRGBTransferOETF( in vec4 value ) {
	return vec4( mix( pow( value.rgb, vec3( 0.41666 ) ) * 1.055 - vec3( 0.055 ), value.rgb * 12.92, vec3( lessThanEqual( value.rgb, vec3( 0.0031308 ) ) ) ), value.a );
}`,envmap_fragment:`#ifdef USE_ENVMAP
	#ifdef ENV_WORLDPOS
		vec3 cameraToFrag;
		if ( isOrthographic ) {
			cameraToFrag = normalize( vec3( - viewMatrix[ 0 ][ 2 ], - viewMatrix[ 1 ][ 2 ], - viewMatrix[ 2 ][ 2 ] ) );
		} else {
			cameraToFrag = normalize( vWorldPosition - cameraPosition );
		}
		vec3 worldNormal = transformNormalByInverseViewMatrix( normal, viewMatrix );
		#ifdef ENVMAP_MODE_REFLECTION
			vec3 reflectVec = reflect( cameraToFrag, worldNormal );
		#else
			vec3 reflectVec = refract( cameraToFrag, worldNormal, refractionRatio );
		#endif
	#else
		vec3 reflectVec = vReflect;
	#endif
	#ifdef ENVMAP_TYPE_CUBE
		vec4 envColor = textureCube( envMap, envMapRotation * reflectVec );
		#ifdef ENVMAP_BLENDING_MULTIPLY
			outgoingLight = mix( outgoingLight, outgoingLight * envColor.xyz, specularStrength * reflectivity );
		#elif defined( ENVMAP_BLENDING_MIX )
			outgoingLight = mix( outgoingLight, envColor.xyz, specularStrength * reflectivity );
		#elif defined( ENVMAP_BLENDING_ADD )
			outgoingLight += envColor.xyz * specularStrength * reflectivity;
		#endif
	#endif
#endif`,envmap_common_pars_fragment:`#ifdef USE_ENVMAP
	uniform float envMapIntensity;
	uniform mat3 envMapRotation;
	#ifdef ENVMAP_TYPE_CUBE
		uniform samplerCube envMap;
	#else
		uniform sampler2D envMap;
	#endif
#endif`,envmap_pars_fragment:`#ifdef USE_ENVMAP
	uniform float reflectivity;
	#if defined( USE_BUMPMAP ) || defined( USE_NORMALMAP ) || defined( PHONG ) || defined( LAMBERT )
		#define ENV_WORLDPOS
	#endif
	#ifdef ENV_WORLDPOS
		varying vec3 vWorldPosition;
		uniform float refractionRatio;
	#else
		varying vec3 vReflect;
	#endif
#endif`,envmap_pars_vertex:`#ifdef USE_ENVMAP
	#if defined( USE_BUMPMAP ) || defined( USE_NORMALMAP ) || defined( PHONG ) || defined( LAMBERT )
		#define ENV_WORLDPOS
	#endif
	#ifdef ENV_WORLDPOS
		
		varying vec3 vWorldPosition;
	#else
		varying vec3 vReflect;
		uniform float refractionRatio;
	#endif
#endif`,envmap_physical_pars_fragment:`#ifdef USE_ENVMAP
	vec3 getIBLIrradiance( const in vec3 normal ) {
		#ifdef ENVMAP_TYPE_CUBE_UV
			vec3 worldNormal = transformNormalByInverseViewMatrix( normal, viewMatrix );
			vec4 envMapColor = textureCubeUV( envMap, envMapRotation * worldNormal, 1.0 );
			return PI * envMapColor.rgb * envMapIntensity;
		#else
			return vec3( 0.0 );
		#endif
	}
	vec3 getIBLRadiance( const in vec3 viewDir, const in vec3 normal, const in float roughness ) {
		#ifdef ENVMAP_TYPE_CUBE_UV
			vec3 reflectVec = reflect( - viewDir, normal );
			reflectVec = normalize( mix( reflectVec, normal, pow4( roughness ) ) );
			reflectVec = transformDirectionByInverseViewMatrix( reflectVec, viewMatrix );
			vec4 envMapColor = textureCubeUV( envMap, envMapRotation * reflectVec, roughness );
			return envMapColor.rgb * envMapIntensity;
		#else
			return vec3( 0.0 );
		#endif
	}
	#ifdef USE_RETROREFLECTION
		vec3 getIBLRetroRadiance( const in vec3 viewDir, const in vec3 normal, const in float roughness ) {
			#ifdef ENVMAP_TYPE_CUBE_UV
				vec3 retroVec = normalize( mix( viewDir, normal, pow4( roughness ) ) );
				retroVec = transformDirectionByInverseViewMatrix( retroVec, viewMatrix );
				vec4 envMapColor = textureCubeUV( envMap, envMapRotation * retroVec, roughness );
				return envMapColor.rgb * envMapIntensity;
			#else
				return vec3( 0.0 );
			#endif
		}
	#endif
	#ifdef USE_ANISOTROPY
		vec3 getIBLAnisotropyRadiance( const in vec3 viewDir, const in vec3 normal, const in float roughness, const in vec3 bitangent, const in float anisotropy ) {
			#ifdef ENVMAP_TYPE_CUBE_UV
				vec3 bentNormal = cross( bitangent, viewDir );
				bentNormal = normalize( cross( bentNormal, bitangent ) );
				bentNormal = normalize( mix( bentNormal, normal, pow2( pow2( 1.0 - anisotropy * ( 1.0 - roughness ) ) ) ) );
				return getIBLRadiance( viewDir, bentNormal, roughness );
			#else
				return vec3( 0.0 );
			#endif
		}
		#ifdef USE_RETROREFLECTION
			vec3 getIBLAnisotropyRetroRadiance( const in vec3 viewDir, const in vec3 normal, const in float roughness, const in vec3 bitangent, const in float anisotropy ) {
				#ifdef ENVMAP_TYPE_CUBE_UV
					vec3 bentNormal = cross( bitangent, viewDir );
					bentNormal = normalize( cross( bentNormal, bitangent ) );
					bentNormal = normalize( mix( bentNormal, normal, pow2( pow2( 1.0 - anisotropy * ( 1.0 - roughness ) ) ) ) );
					return getIBLRetroRadiance( viewDir, bentNormal, roughness );
				#else
					return vec3( 0.0 );
				#endif
			}
		#endif
	#endif
#endif`,envmap_vertex:`#ifdef USE_ENVMAP
	#ifdef ENV_WORLDPOS
		vWorldPosition = worldPosition.xyz;
	#else
		vec3 cameraToVertex;
		if ( isOrthographic ) {
			cameraToVertex = normalize( vec3( - viewMatrix[ 0 ][ 2 ], - viewMatrix[ 1 ][ 2 ], - viewMatrix[ 2 ][ 2 ] ) );
		} else {
			cameraToVertex = normalize( worldPosition.xyz - cameraPosition );
		}
		vec3 worldNormal = transformNormalByInverseViewMatrix( transformedNormal, viewMatrix );
		#ifdef ENVMAP_MODE_REFLECTION
			vReflect = reflect( cameraToVertex, worldNormal );
		#else
			vReflect = refract( cameraToVertex, worldNormal, refractionRatio );
		#endif
	#endif
#endif`,fog_vertex:`#ifdef USE_FOG
	vFogDepth = - mvPosition.z;
#endif`,fog_pars_vertex:`#ifdef USE_FOG
	varying float vFogDepth;
#endif`,fog_fragment:`#ifdef USE_FOG
	#ifdef FOG_EXP2
		float fogFactor = 1.0 - exp( - fogDensity * fogDensity * vFogDepth * vFogDepth );
	#else
		float fogFactor = smoothstep( fogNear, fogFar, vFogDepth );
	#endif
	gl_FragColor.rgb = mix( gl_FragColor.rgb, fogColor, fogFactor );
#endif`,fog_pars_fragment:`#ifdef USE_FOG
	uniform vec3 fogColor;
	varying float vFogDepth;
	#ifdef FOG_EXP2
		uniform float fogDensity;
	#else
		uniform float fogNear;
		uniform float fogFar;
	#endif
#endif`,gradientmap_pars_fragment:`#ifdef USE_GRADIENTMAP
	uniform sampler2D gradientMap;
#endif
vec3 getGradientIrradiance( vec3 normal, vec3 lightDirection ) {
	float dotNL = dot( normal, lightDirection );
	vec2 coord = vec2( dotNL * 0.5 + 0.5, 0.0 );
	#ifdef USE_GRADIENTMAP
		return vec3( texture2D( gradientMap, coord ).r );
	#else
		vec2 fw = fwidth( coord ) * 0.5;
		return mix( vec3( 0.7 ), vec3( 1.0 ), smoothstep( 0.7 - fw.x, 0.7 + fw.x, coord.x ) );
	#endif
}`,lightmap_pars_fragment:`#ifdef USE_LIGHTMAP
	uniform sampler2D lightMap;
	uniform float lightMapIntensity;
#endif`,lights_lambert_fragment:`LambertMaterial material;
material.diffuseColor = diffuseColor.rgb;
material.specularStrength = specularStrength;`,lights_lambert_pars_fragment:`varying vec3 vViewPosition;
struct LambertMaterial {
	vec3 diffuseColor;
	float specularStrength;
};
void RE_Direct_Lambert( const in IncidentLight directLight, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in LambertMaterial material, inout ReflectedLight reflectedLight ) {
	float dotNL = saturate( dot( geometryNormal, directLight.direction ) );
	vec3 irradiance = dotNL * directLight.color;
	reflectedLight.directDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
}
void RE_IndirectDiffuse_Lambert( const in vec3 irradiance, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in LambertMaterial material, inout ReflectedLight reflectedLight ) {
	reflectedLight.indirectDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
}
#define RE_Direct				RE_Direct_Lambert
#define RE_IndirectDiffuse		RE_IndirectDiffuse_Lambert`,lights_pars_begin:`uniform bool receiveShadow;
uniform vec3 ambientLightColor;
#if defined( USE_LIGHT_PROBES )
	uniform vec3 lightProbe[ 9 ];
#endif
vec3 shGetIrradianceAt( in vec3 normal, in vec3 shCoefficients[ 9 ] ) {
	float x = normal.x, y = normal.y, z = normal.z;
	vec3 result = shCoefficients[ 0 ] * 0.886227;
	result += shCoefficients[ 1 ] * 2.0 * 0.511664 * y;
	result += shCoefficients[ 2 ] * 2.0 * 0.511664 * z;
	result += shCoefficients[ 3 ] * 2.0 * 0.511664 * x;
	result += shCoefficients[ 4 ] * 2.0 * 0.429043 * x * y;
	result += shCoefficients[ 5 ] * 2.0 * 0.429043 * y * z;
	result += shCoefficients[ 6 ] * ( 0.743125 * z * z - 0.247708 );
	result += shCoefficients[ 7 ] * 2.0 * 0.429043 * x * z;
	result += shCoefficients[ 8 ] * 0.429043 * ( x * x - y * y );
	return result;
}
vec3 getLightProbeIrradiance( const in vec3 lightProbe[ 9 ], const in vec3 normal ) {
	vec3 worldNormal = transformNormalByInverseViewMatrix( normal, viewMatrix );
	vec3 irradiance = shGetIrradianceAt( worldNormal, lightProbe );
	return irradiance;
}
vec3 getAmbientLightIrradiance( const in vec3 ambientLightColor ) {
	vec3 irradiance = ambientLightColor;
	return irradiance;
}
float getDistanceAttenuation( const in float lightDistance, const in float cutoffDistance, const in float decayExponent ) {
	float distanceFalloff = 1.0 / max( pow( lightDistance, decayExponent ), 0.01 );
	if ( cutoffDistance > 0.0 ) {
		distanceFalloff *= pow2( saturate( 1.0 - pow4( lightDistance / cutoffDistance ) ) );
	}
	return distanceFalloff;
}
float getSpotAttenuation( const in float coneCosine, const in float penumbraCosine, const in float angleCosine ) {
	return smoothstep( coneCosine, penumbraCosine, angleCosine );
}
#if NUM_SUN_LIGHTS > 0
	struct SunLight {
		vec3 direction;
		vec3 color;
	};
	uniform SunLight sunLights[ NUM_SUN_LIGHTS ];
	void getSunLightInfo( const in SunLight sunLight, out IncidentLight light ) {
		light.color = sunLight.color;
		light.direction = sunLight.direction;
		light.visible = true;
	}
#endif
#if NUM_DIR_LIGHTS > 0
	struct DirectionalLight {
		vec3 direction;
		vec3 color;
	};
	uniform DirectionalLight directionalLights[ NUM_DIR_LIGHTS ];
	void getDirectionalLightInfo( const in DirectionalLight directionalLight, out IncidentLight light ) {
		light.color = directionalLight.color;
		light.direction = directionalLight.direction;
		light.visible = true;
	}
#endif
#if NUM_POINT_LIGHTS > 0
	struct PointLight {
		vec3 position;
		vec3 color;
		float distance;
		float decay;
	};
	uniform PointLight pointLights[ NUM_POINT_LIGHTS ];
	void getPointLightInfo( const in PointLight pointLight, const in vec3 geometryPosition, out IncidentLight light ) {
		vec3 lVector = pointLight.position - geometryPosition;
		light.direction = normalize( lVector );
		float lightDistance = length( lVector );
		light.color = pointLight.color;
		light.color *= getDistanceAttenuation( lightDistance, pointLight.distance, pointLight.decay );
		light.visible = ( light.color != vec3( 0.0 ) );
	}
#endif
#if NUM_SPOT_LIGHTS > 0
	struct SpotLight {
		vec3 position;
		vec3 direction;
		vec3 color;
		float distance;
		float decay;
		float coneCos;
		float penumbraCos;
	};
	uniform SpotLight spotLights[ NUM_SPOT_LIGHTS ];
	void getSpotLightInfo( const in SpotLight spotLight, const in vec3 geometryPosition, out IncidentLight light ) {
		vec3 lVector = spotLight.position - geometryPosition;
		light.direction = normalize( lVector );
		float angleCos = dot( light.direction, spotLight.direction );
		float spotAttenuation = getSpotAttenuation( spotLight.coneCos, spotLight.penumbraCos, angleCos );
		if ( spotAttenuation > 0.0 ) {
			float lightDistance = length( lVector );
			light.color = spotLight.color * spotAttenuation;
			light.color *= getDistanceAttenuation( lightDistance, spotLight.distance, spotLight.decay );
			light.visible = ( light.color != vec3( 0.0 ) );
		} else {
			light.color = vec3( 0.0 );
			light.visible = false;
		}
	}
#endif
#if NUM_RECT_AREA_LIGHTS > 0
	struct RectAreaLight {
		vec3 color;
		vec3 position;
		vec3 halfWidth;
		vec3 halfHeight;
	};
	uniform sampler2D ltc_1;	uniform sampler2D ltc_2;
	uniform RectAreaLight rectAreaLights[ NUM_RECT_AREA_LIGHTS ];
#endif
#if NUM_HEMI_LIGHTS > 0
	struct HemisphereLight {
		vec3 direction;
		vec3 skyColor;
		vec3 groundColor;
	};
	uniform HemisphereLight hemisphereLights[ NUM_HEMI_LIGHTS ];
	vec3 getHemisphereLightIrradiance( const in HemisphereLight hemiLight, const in vec3 normal ) {
		float dotNL = dot( normal, hemiLight.direction );
		float hemiDiffuseWeight = 0.5 * dotNL + 0.5;
		vec3 irradiance = mix( hemiLight.groundColor, hemiLight.skyColor, hemiDiffuseWeight );
		return irradiance;
	}
#endif
#include <lightprobes_pars_fragment>`,lights_toon_fragment:`ToonMaterial material;
material.diffuseColor = diffuseColor.rgb;`,lights_toon_pars_fragment:`varying vec3 vViewPosition;
struct ToonMaterial {
	vec3 diffuseColor;
};
void RE_Direct_Toon( const in IncidentLight directLight, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in ToonMaterial material, inout ReflectedLight reflectedLight ) {
	vec3 irradiance = getGradientIrradiance( geometryNormal, directLight.direction ) * directLight.color;
	reflectedLight.directDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
}
void RE_IndirectDiffuse_Toon( const in vec3 irradiance, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in ToonMaterial material, inout ReflectedLight reflectedLight ) {
	reflectedLight.indirectDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
}
#define RE_Direct				RE_Direct_Toon
#define RE_IndirectDiffuse		RE_IndirectDiffuse_Toon`,lights_phong_fragment:`BlinnPhongMaterial material;
material.diffuseColor = diffuseColor.rgb;
material.specularColor = specular;
material.specularShininess = shininess;
material.specularStrength = specularStrength;`,lights_phong_pars_fragment:`varying vec3 vViewPosition;
struct BlinnPhongMaterial {
	vec3 diffuseColor;
	vec3 specularColor;
	float specularShininess;
	float specularStrength;
};
void RE_Direct_BlinnPhong( const in IncidentLight directLight, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in BlinnPhongMaterial material, inout ReflectedLight reflectedLight ) {
	float dotNL = saturate( dot( geometryNormal, directLight.direction ) );
	vec3 irradiance = dotNL * directLight.color;
	reflectedLight.directDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
	reflectedLight.directSpecular += irradiance * BRDF_BlinnPhong( directLight.direction, geometryViewDir, geometryNormal, material.specularColor, material.specularShininess ) * material.specularStrength;
}
void RE_IndirectDiffuse_BlinnPhong( const in vec3 irradiance, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in BlinnPhongMaterial material, inout ReflectedLight reflectedLight ) {
	reflectedLight.indirectDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
}
#define RE_Direct				RE_Direct_BlinnPhong
#define RE_IndirectDiffuse		RE_IndirectDiffuse_BlinnPhong`,lights_physical_fragment:`PhysicalMaterial material;
material.diffuseColor = diffuseColor.rgb;
material.diffuseContribution = diffuseColor.rgb * ( 1.0 - metalnessFactor );
material.metalness = metalnessFactor;
vec3 dxy = max( abs( dFdx( nonPerturbedNormal ) ), abs( dFdy( nonPerturbedNormal ) ) );
float geometryRoughness = max( max( dxy.x, dxy.y ), dxy.z );
material.roughness = max( roughnessFactor, 0.0525 );material.roughness += geometryRoughness;
material.roughness = min( material.roughness, 1.0 );
#ifdef IOR
	material.ior = ior;
	#ifdef USE_SPECULAR
		float specularIntensityFactor = specularIntensity;
		vec3 specularColorFactor = specularColor;
		#ifdef USE_SPECULAR_COLORMAP
			specularColorFactor *= texture2D( specularColorMap, vSpecularColorMapUv ).rgb;
		#endif
		#ifdef USE_SPECULAR_INTENSITYMAP
			specularIntensityFactor *= texture2D( specularIntensityMap, vSpecularIntensityMapUv ).a;
		#endif
		material.specularF90 = mix( specularIntensityFactor, 1.0, metalnessFactor );
	#else
		float specularIntensityFactor = 1.0;
		vec3 specularColorFactor = vec3( 1.0 );
		material.specularF90 = 1.0;
	#endif
	material.specularColor = min( pow2( ( material.ior - 1.0 ) / ( material.ior + 1.0 ) ) * specularColorFactor, vec3( 1.0 ) ) * specularIntensityFactor;
	material.specularColorBlended = mix( material.specularColor, diffuseColor.rgb, metalnessFactor );
#else
	material.specularColor = vec3( 0.04 );
	material.specularColorBlended = mix( material.specularColor, diffuseColor.rgb, metalnessFactor );
	material.specularF90 = 1.0;
#endif
#ifdef USE_CLEARCOAT
	material.clearcoat = clearcoat;
	material.clearcoatRoughness = clearcoatRoughness;
	material.clearcoatF0 = vec3( 0.04 );
	material.clearcoatF90 = 1.0;
	#ifdef USE_CLEARCOATMAP
		material.clearcoat *= texture2D( clearcoatMap, vClearcoatMapUv ).x;
	#endif
	#ifdef USE_CLEARCOAT_ROUGHNESSMAP
		material.clearcoatRoughness *= texture2D( clearcoatRoughnessMap, vClearcoatRoughnessMapUv ).y;
	#endif
	material.clearcoat = saturate( material.clearcoat );	material.clearcoatRoughness = max( material.clearcoatRoughness, 0.0525 );
	material.clearcoatRoughness += geometryRoughness;
	material.clearcoatRoughness = min( material.clearcoatRoughness, 1.0 );
#endif
#ifdef USE_DISPERSION
	material.dispersion = dispersion;
#endif
#ifdef USE_RETROREFLECTION
	material.retroreflectivity = retroreflectivity;
#endif
#ifdef USE_IRIDESCENCE
	material.iridescence = iridescence;
	material.iridescenceIOR = iridescenceIOR;
	#ifdef USE_IRIDESCENCEMAP
		material.iridescence *= texture2D( iridescenceMap, vIridescenceMapUv ).r;
	#endif
	#ifdef USE_IRIDESCENCE_THICKNESSMAP
		material.iridescenceThickness = (iridescenceThicknessMaximum - iridescenceThicknessMinimum) * texture2D( iridescenceThicknessMap, vIridescenceThicknessMapUv ).g + iridescenceThicknessMinimum;
	#else
		material.iridescenceThickness = iridescenceThicknessMaximum;
	#endif
#endif
#ifdef USE_SHEEN
	material.sheenColor = sheenColor;
	#ifdef USE_SHEEN_COLORMAP
		material.sheenColor *= texture2D( sheenColorMap, vSheenColorMapUv ).rgb;
	#endif
	material.sheenRoughness = clamp( sheenRoughness, 0.0001, 1.0 );
	#ifdef USE_SHEEN_ROUGHNESSMAP
		material.sheenRoughness *= texture2D( sheenRoughnessMap, vSheenRoughnessMapUv ).a;
	#endif
#endif
#ifdef USE_ANISOTROPY
	#ifdef USE_ANISOTROPYMAP
		mat2 anisotropyMat = mat2( anisotropyVector.x, anisotropyVector.y, - anisotropyVector.y, anisotropyVector.x );
		vec3 anisotropyPolar = texture2D( anisotropyMap, vAnisotropyMapUv ).rgb;
		vec2 anisotropyV = anisotropyMat * normalize( 2.0 * anisotropyPolar.rg - vec2( 1.0 ) ) * anisotropyPolar.b;
	#else
		vec2 anisotropyV = anisotropyVector;
	#endif
	material.anisotropy = length( anisotropyV );
	if( material.anisotropy == 0.0 ) {
		anisotropyV = vec2( 1.0, 0.0 );
	} else {
		anisotropyV /= material.anisotropy;
		material.anisotropy = saturate( material.anisotropy );
	}
	material.alphaT = mix( pow2( material.roughness ), 1.0, pow2( material.anisotropy ) );
	material.anisotropyT = tbn[ 0 ] * anisotropyV.x + tbn[ 1 ] * anisotropyV.y;
	material.anisotropyB = tbn[ 1 ] * anisotropyV.x - tbn[ 0 ] * anisotropyV.y;
#endif`,lights_physical_pars_fragment:`uniform sampler2D dfgLUT;
struct PhysicalMaterial {
	vec3 diffuseColor;
	vec3 diffuseContribution;
	vec3 specularColor;
	vec3 specularColorBlended;
	float roughness;
	float metalness;
	float specularF90;
	float dispersion;
	vec2 dfg;
	vec3 multiScatteringCompensation;
	#ifdef USE_RETROREFLECTION
		float retroreflectivity;
	#endif
	#ifdef USE_CLEARCOAT
		float clearcoat;
		float clearcoatRoughness;
		vec3 clearcoatF0;
		float clearcoatF90;
	#endif
	#ifdef USE_IRIDESCENCE
		float iridescence;
		float iridescenceIOR;
		float iridescenceThickness;
		vec3 iridescenceFresnel;
		vec3 iridescenceF0Dielectric;
		vec3 iridescenceF0Metallic;
	#endif
	#ifdef USE_SHEEN
		vec3 sheenColor;
		float sheenRoughness;
	#endif
	#ifdef IOR
		float ior;
	#endif
	#ifdef USE_TRANSMISSION
		float transmission;
		float transmissionAlpha;
		float thickness;
		float attenuationDistance;
		vec3 attenuationColor;
	#endif
	#ifdef USE_ANISOTROPY
		float anisotropy;
		float alphaT;
		vec3 anisotropyT;
		vec3 anisotropyB;
	#endif
};
vec3 clearcoatSpecularDirect = vec3( 0.0 );
vec3 clearcoatSpecularIndirect = vec3( 0.0 );
vec3 sheenSpecularDirect = vec3( 0.0 );
vec3 sheenSpecularIndirect = vec3(0.0 );
vec3 Schlick_to_F0( const in vec3 f, const in float f90, const in float dotVH ) {
    float x = clamp( 1.0 - dotVH, 0.0, 1.0 );
    float x2 = x * x;
    float x5 = clamp( x * x2 * x2, 0.0, 0.9999 );
    return ( f - vec3( f90 ) * x5 ) / ( 1.0 - x5 );
}
float V_GGX_SmithCorrelated( const in float alpha, const in float dotNL, const in float dotNV ) {
	float a2 = pow2( alpha );
	float gv = dotNL * sqrt( a2 + ( 1.0 - a2 ) * pow2( dotNV ) );
	float gl = dotNV * sqrt( a2 + ( 1.0 - a2 ) * pow2( dotNL ) );
	return 0.5 / max( gv + gl, EPSILON );
}
float D_GGX( const in float alpha, const in float dotNH ) {
	float a2 = pow2( alpha );
	float denom = pow2( dotNH ) * ( a2 - 1.0 ) + 1.0;
	return RECIPROCAL_PI * a2 / pow2( denom );
}
#ifdef USE_ANISOTROPY
	float V_GGX_SmithCorrelated_Anisotropic( const in float alphaT, const in float alphaB, const in float dotTV, const in float dotBV, const in float dotTL, const in float dotBL, const in float dotNV, const in float dotNL ) {
		float gv = dotNL * length( vec3( alphaT * dotTV, alphaB * dotBV, dotNV ) );
		float gl = dotNV * length( vec3( alphaT * dotTL, alphaB * dotBL, dotNL ) );
		return 0.5 / max( gv + gl, EPSILON );
	}
	float D_GGX_Anisotropic( const in float alphaT, const in float alphaB, const in float dotNH, const in float dotTH, const in float dotBH ) {
		float a2 = alphaT * alphaB;
		highp vec3 v = vec3( alphaB * dotTH, alphaT * dotBH, a2 * dotNH );
		highp float v2 = dot( v, v );
		float w2 = a2 / v2;
		return RECIPROCAL_PI * a2 * pow2 ( w2 );
	}
#endif
#ifdef USE_CLEARCOAT
	vec3 BRDF_GGX_Clearcoat( const in vec3 lightDir, const in vec3 viewDir, const in vec3 normal, const in PhysicalMaterial material) {
		vec3 f0 = material.clearcoatF0;
		float f90 = material.clearcoatF90;
		float roughness = material.clearcoatRoughness;
		float alpha = pow2( roughness );
		vec3 halfDir = normalize( lightDir + viewDir );
		float dotNL = saturate( dot( normal, lightDir ) );
		float dotNV = saturate( dot( normal, viewDir ) );
		float dotNH = saturate( dot( normal, halfDir ) );
		float dotVH = saturate( dot( viewDir, halfDir ) );
		vec3 F = F_Schlick( f0, f90, dotVH );
		float V = V_GGX_SmithCorrelated( alpha, dotNL, dotNV );
		float D = D_GGX( alpha, dotNH );
		return F * ( V * D );
	}
#endif
vec3 BRDF_GGX( const in vec3 lightDir, const in vec3 viewDir, const in vec3 normal, const in PhysicalMaterial material ) {
	vec3 f0 = material.specularColorBlended;
	float f90 = material.specularF90;
	float roughness = material.roughness;
	float alpha = pow2( roughness );
	vec3 halfDir = normalize( lightDir + viewDir );
	float dotNL = saturate( dot( normal, lightDir ) );
	float dotNV = saturate( dot( normal, viewDir ) );
	float dotNH = saturate( dot( normal, halfDir ) );
	float dotVH = saturate( dot( viewDir, halfDir ) );
	vec3 F = F_Schlick( f0, f90, dotVH );
	#ifdef USE_IRIDESCENCE
		F = mix( F, material.iridescenceFresnel, material.iridescence );
	#endif
	#ifdef USE_ANISOTROPY
		float dotTL = dot( material.anisotropyT, lightDir );
		float dotTV = dot( material.anisotropyT, viewDir );
		float dotTH = dot( material.anisotropyT, halfDir );
		float dotBL = dot( material.anisotropyB, lightDir );
		float dotBV = dot( material.anisotropyB, viewDir );
		float dotBH = dot( material.anisotropyB, halfDir );
		float V = V_GGX_SmithCorrelated_Anisotropic( material.alphaT, alpha, dotTV, dotBV, dotTL, dotBL, dotNV, dotNL );
		float D = D_GGX_Anisotropic( material.alphaT, alpha, dotNH, dotTH, dotBH );
	#else
		float V = V_GGX_SmithCorrelated( alpha, dotNL, dotNV );
		float D = D_GGX( alpha, dotNH );
	#endif
	return F * ( V * D );
}
vec2 LTC_Uv( const in vec3 N, const in vec3 V, const in float roughness ) {
	const float LUT_SIZE = 64.0;
	const float LUT_SCALE = ( LUT_SIZE - 1.0 ) / LUT_SIZE;
	const float LUT_BIAS = 0.5 / LUT_SIZE;
	float dotNV = saturate( dot( N, V ) );
	vec2 uv = vec2( roughness, sqrt( 1.0 - dotNV ) );
	uv = uv * LUT_SCALE + LUT_BIAS;
	return uv;
}
float LTC_ClippedSphereFormFactor( const in vec3 f ) {
	float l = length( f );
	return max( ( l * l + f.z ) / ( l + 1.0 ), 0.0 );
}
vec3 LTC_EdgeVectorFormFactor( const in vec3 v1, const in vec3 v2 ) {
	float x = dot( v1, v2 );
	float y = abs( x );
	float a = 0.8543985 + ( 0.4965155 + 0.0145206 * y ) * y;
	float b = 3.4175940 + ( 4.1616724 + y ) * y;
	float v = a / b;
	float theta_sintheta = ( x > 0.0 ) ? v : 0.5 * inversesqrt( max( 1.0 - x * x, 1e-7 ) ) - v;
	return cross( v1, v2 ) * theta_sintheta;
}
vec3 LTC_Evaluate( const in vec3 N, const in vec3 V, const in vec3 P, const in mat3 mInv, const in vec3 rectCoords[ 4 ] ) {
	vec3 v1 = rectCoords[ 1 ] - rectCoords[ 0 ];
	vec3 v2 = rectCoords[ 3 ] - rectCoords[ 0 ];
	vec3 lightNormal = cross( v1, v2 );
	if( dot( lightNormal, P - rectCoords[ 0 ] ) < 0.0 ) return vec3( 0.0 );
	vec3 T1, T2;
	T1 = normalize( V - N * dot( V, N ) );
	T2 = - cross( N, T1 );
	mat3 mat = mInv * transpose( mat3( T1, T2, N ) );
	vec3 coords[ 4 ];
	coords[ 0 ] = mat * ( rectCoords[ 0 ] - P );
	coords[ 1 ] = mat * ( rectCoords[ 1 ] - P );
	coords[ 2 ] = mat * ( rectCoords[ 2 ] - P );
	coords[ 3 ] = mat * ( rectCoords[ 3 ] - P );
	coords[ 0 ] = normalize( coords[ 0 ] );
	coords[ 1 ] = normalize( coords[ 1 ] );
	coords[ 2 ] = normalize( coords[ 2 ] );
	coords[ 3 ] = normalize( coords[ 3 ] );
	vec3 vectorFormFactor = vec3( 0.0 );
	vectorFormFactor += LTC_EdgeVectorFormFactor( coords[ 0 ], coords[ 1 ] );
	vectorFormFactor += LTC_EdgeVectorFormFactor( coords[ 1 ], coords[ 2 ] );
	vectorFormFactor += LTC_EdgeVectorFormFactor( coords[ 2 ], coords[ 3 ] );
	vectorFormFactor += LTC_EdgeVectorFormFactor( coords[ 3 ], coords[ 0 ] );
	float result = LTC_ClippedSphereFormFactor( vectorFormFactor );
	return vec3( result );
}
#if defined( USE_SHEEN )
float D_Charlie( float roughness, float dotNH ) {
	float alpha = pow2( roughness );
	float invAlpha = 1.0 / alpha;
	float cos2h = dotNH * dotNH;
	float sin2h = max( 1.0 - cos2h, 0.0078125 );
	return ( 2.0 + invAlpha ) * pow( sin2h, invAlpha * 0.5 ) / ( 2.0 * PI );
}
float V_Neubelt( float dotNV, float dotNL ) {
	return saturate( 1.0 / ( 4.0 * ( dotNL + dotNV - dotNL * dotNV ) ) );
}
vec3 BRDF_Sheen( const in vec3 lightDir, const in vec3 viewDir, const in vec3 normal, vec3 sheenColor, const in float sheenRoughness ) {
	vec3 halfDir = normalize( lightDir + viewDir );
	float dotNL = saturate( dot( normal, lightDir ) );
	float dotNV = saturate( dot( normal, viewDir ) );
	float dotNH = saturate( dot( normal, halfDir ) );
	float D = D_Charlie( sheenRoughness, dotNH );
	float V = V_Neubelt( dotNV, dotNL );
	return sheenColor * ( D * V );
}
#endif
float IBLSheenBRDF( const in vec3 normal, const in vec3 viewDir, const in float roughness ) {
	float dotNV = saturate( dot( normal, viewDir ) );
	float r2 = roughness * roughness;
	float rInv = 1.0 / ( roughness + 0.1 );
	float a = -1.9362 + 1.0678 * roughness + 0.4573 * r2 - 0.8469 * rInv;
	float b = -0.6014 + 0.5538 * roughness - 0.4670 * r2 - 0.1255 * rInv;
	float DG = exp( a * dotNV + b );
	return saturate( DG );
}
vec3 EnvironmentBRDF( const in vec3 normal, const in vec3 viewDir, const in vec3 specularColor, const in float specularF90, const in float roughness ) {
	float dotNV = saturate( dot( normal, viewDir ) );
	vec2 fab = texture2D( dfgLUT, vec2( roughness, dotNV ) ).rg;
	return specularColor * fab.x + specularF90 * fab.y;
}
#ifdef USE_IRIDESCENCE
void computeMultiscatteringIridescence( const in vec2 fab, const in vec3 specularColor, const in float specularF90, const in float iridescence, const in vec3 iridescenceF0, inout vec3 singleScatter, inout vec3 multiScatter ) {
#else
void computeMultiscattering( const in vec2 fab, const in vec3 specularColor, const in float specularF90, inout vec3 singleScatter, inout vec3 multiScatter ) {
#endif
	#ifdef USE_IRIDESCENCE
		vec3 Fr = mix( specularColor, iridescenceF0, iridescence );
	#else
		vec3 Fr = specularColor;
	#endif
	vec3 FssEss = Fr * fab.x + specularF90 * fab.y;
	float Ess = fab.x + fab.y;
	float Ems = 1.0 - Ess;
	vec3 Favg = Fr + ( 1.0 - Fr ) * 0.047619;	vec3 Fms = FssEss * Favg / ( 1.0 - Ems * Favg );
	singleScatter += FssEss;
	multiScatter += Fms * Ems;
}
#if NUM_RECT_AREA_LIGHTS > 0
	void RE_Direct_RectArea_Physical( const in RectAreaLight rectAreaLight, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in PhysicalMaterial material, inout ReflectedLight reflectedLight ) {
		vec3 normal = geometryNormal;
		vec3 viewDir = geometryViewDir;
		vec3 position = geometryPosition;
		vec3 lightPos = rectAreaLight.position;
		vec3 halfWidth = rectAreaLight.halfWidth;
		vec3 halfHeight = rectAreaLight.halfHeight;
		vec3 lightColor = rectAreaLight.color;
		float roughness = material.roughness;
		vec3 rectCoords[ 4 ];
		rectCoords[ 0 ] = lightPos + halfWidth - halfHeight;		rectCoords[ 1 ] = lightPos - halfWidth - halfHeight;
		rectCoords[ 2 ] = lightPos - halfWidth + halfHeight;
		rectCoords[ 3 ] = lightPos + halfWidth + halfHeight;
		vec2 uv = LTC_Uv( normal, viewDir, roughness );
		vec4 t1 = texture2D( ltc_1, uv );
		vec4 t2 = texture2D( ltc_2, uv );
		mat3 mInv = mat3(
			vec3( t1.x, 0, t1.y ),
			vec3(    0, 1,    0 ),
			vec3( t1.z, 0, t1.w )
		);
		vec3 fresnel = ( material.specularColorBlended * t2.x + ( material.specularF90 - material.specularColorBlended ) * t2.y );
		reflectedLight.directSpecular += lightColor * fresnel * LTC_Evaluate( normal, viewDir, position, mInv, rectCoords );
		reflectedLight.directDiffuse += lightColor * material.diffuseContribution * LTC_Evaluate( normal, viewDir, position, mat3( 1.0 ), rectCoords );
		#ifdef USE_CLEARCOAT
			vec3 Ncc = geometryClearcoatNormal;
			vec2 uvClearcoat = LTC_Uv( Ncc, viewDir, material.clearcoatRoughness );
			vec4 t1Clearcoat = texture2D( ltc_1, uvClearcoat );
			vec4 t2Clearcoat = texture2D( ltc_2, uvClearcoat );
			mat3 mInvClearcoat = mat3(
				vec3( t1Clearcoat.x, 0, t1Clearcoat.y ),
				vec3(             0, 1,             0 ),
				vec3( t1Clearcoat.z, 0, t1Clearcoat.w )
			);
			vec3 fresnelClearcoat = material.clearcoatF0 * t2Clearcoat.x + ( material.clearcoatF90 - material.clearcoatF0 ) * t2Clearcoat.y;
			clearcoatSpecularDirect += lightColor * fresnelClearcoat * LTC_Evaluate( Ncc, viewDir, position, mInvClearcoat, rectCoords );
		#endif
	}
#endif
void RE_Direct_Physical( const in IncidentLight directLight, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in PhysicalMaterial material, inout ReflectedLight reflectedLight ) {
	float dotNL = saturate( dot( geometryNormal, directLight.direction ) );
	vec3 irradiance = dotNL * directLight.color;
	#ifdef USE_CLEARCOAT
		float dotNLcc = saturate( dot( geometryClearcoatNormal, directLight.direction ) );
		vec3 ccIrradiance = dotNLcc * directLight.color;
		clearcoatSpecularDirect += ccIrradiance * BRDF_GGX_Clearcoat( directLight.direction, geometryViewDir, geometryClearcoatNormal, material );
	#endif
	#ifdef USE_SHEEN
 
 		sheenSpecularDirect += irradiance * BRDF_Sheen( directLight.direction, geometryViewDir, geometryNormal, material.sheenColor, material.sheenRoughness );
 
 		float sheenAlbedoV = IBLSheenBRDF( geometryNormal, geometryViewDir, material.sheenRoughness );
 		float sheenAlbedoL = IBLSheenBRDF( geometryNormal, directLight.direction, material.sheenRoughness );
 
 		float sheenEnergyComp = 1.0 - max3( material.sheenColor ) * max( sheenAlbedoV, sheenAlbedoL );
 
 		irradiance *= sheenEnergyComp;
 
 	#endif
	vec3 specularBRDF = BRDF_GGX( directLight.direction, geometryViewDir, geometryNormal, material );
	#ifdef USE_RETROREFLECTION
		vec3 retroViewDir = reflect( - geometryViewDir, geometryNormal );
		vec3 retroSpecularBRDF = BRDF_GGX( directLight.direction, retroViewDir, geometryNormal, material );
		specularBRDF = mix( specularBRDF, retroSpecularBRDF, saturate( material.retroreflectivity ) );
	#endif
	reflectedLight.directSpecular += irradiance * specularBRDF * material.multiScatteringCompensation;
	vec3 halfDir = normalize( directLight.direction + geometryViewDir );
	float dotVH = saturate( dot( geometryViewDir, halfDir ) );
	vec3 F = F_Schlick( material.specularColor, material.specularF90, dotVH );
	#ifdef USE_RETROREFLECTION
		vec3 retroHalfDir = normalize( directLight.direction + retroViewDir );
		float dotRetroVH = saturate( dot( retroViewDir, retroHalfDir ) );
		vec3 retroF = F_Schlick( material.specularColor, material.specularF90, dotRetroVH );
		F = mix( F, retroF, saturate( material.retroreflectivity ) );
	#endif
	reflectedLight.directDiffuse += irradiance * BRDF_Lambert( material.diffuseContribution ) * ( 1.0 - F );
}
void RE_IndirectDiffuse_Physical( const in vec3 irradiance, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in PhysicalMaterial material, inout ReflectedLight reflectedLight ) {
	vec3 singleScattering = vec3( 0.0 );
	vec3 multiScattering = vec3( 0.0 );
	#ifdef USE_IRIDESCENCE
		computeMultiscatteringIridescence( material.dfg, material.specularColor, material.specularF90, material.iridescence, material.iridescenceF0Dielectric, singleScattering, multiScattering );
	#else
		computeMultiscattering( material.dfg, material.specularColor, material.specularF90, singleScattering, multiScattering );
	#endif
	vec3 diffuse = irradiance * BRDF_Lambert( material.diffuseContribution ) * ( 1.0 - singleScattering - multiScattering );
	#ifdef USE_SHEEN
		float sheenAlbedo = IBLSheenBRDF( geometryNormal, geometryViewDir, material.sheenRoughness );
		sheenSpecularIndirect += irradiance * material.sheenColor * sheenAlbedo * RECIPROCAL_PI;
		float sheenEnergyComp = 1.0 - max3( material.sheenColor ) * sheenAlbedo;
		diffuse *= sheenEnergyComp;
	#endif
	reflectedLight.indirectDiffuse += diffuse;
}
void RE_IndirectSpecular_Physical( const in vec3 radiance, const in vec3 irradiance, const in vec3 clearcoatRadiance, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in PhysicalMaterial material, inout ReflectedLight reflectedLight) {
	#ifdef USE_CLEARCOAT
		clearcoatSpecularIndirect += clearcoatRadiance * EnvironmentBRDF( geometryClearcoatNormal, geometryViewDir, material.clearcoatF0, material.clearcoatF90, material.clearcoatRoughness );
	#endif
	#ifdef USE_SHEEN
		sheenSpecularIndirect += irradiance * material.sheenColor * IBLSheenBRDF( geometryNormal, geometryViewDir, material.sheenRoughness ) * RECIPROCAL_PI;
 	#endif
	vec3 singleScatteringDielectric = vec3( 0.0 );
	vec3 multiScatteringDielectric = vec3( 0.0 );
	vec3 singleScatteringMetallic = vec3( 0.0 );
	vec3 multiScatteringMetallic = vec3( 0.0 );
	#ifdef USE_IRIDESCENCE
		computeMultiscatteringIridescence( material.dfg, material.specularColor, material.specularF90, material.iridescence, material.iridescenceF0Dielectric, singleScatteringDielectric, multiScatteringDielectric );
		computeMultiscatteringIridescence( material.dfg, material.diffuseColor, material.specularF90, material.iridescence, material.iridescenceF0Metallic, singleScatteringMetallic, multiScatteringMetallic );
	#else
		computeMultiscattering( material.dfg, material.specularColor, material.specularF90, singleScatteringDielectric, multiScatteringDielectric );
		computeMultiscattering( material.dfg, material.diffuseColor, material.specularF90, singleScatteringMetallic, multiScatteringMetallic );
	#endif
	vec3 singleScattering = mix( singleScatteringDielectric, singleScatteringMetallic, material.metalness );
	vec3 multiScattering = mix( multiScatteringDielectric, multiScatteringMetallic, material.metalness );
	vec3 totalScatteringDielectric = singleScatteringDielectric + multiScatteringDielectric;
	vec3 diffuse = material.diffuseContribution * ( 1.0 - totalScatteringDielectric );
	vec3 cosineWeightedIrradiance = irradiance * RECIPROCAL_PI;
	vec3 indirectSpecular = radiance * singleScattering;
	indirectSpecular += multiScattering * cosineWeightedIrradiance;
	vec3 indirectDiffuse = diffuse * cosineWeightedIrradiance;
	#ifdef USE_SHEEN
		float sheenAlbedo = IBLSheenBRDF( geometryNormal, geometryViewDir, material.sheenRoughness );
		float sheenEnergyComp = 1.0 - max3( material.sheenColor ) * sheenAlbedo;
		indirectSpecular *= sheenEnergyComp;
		indirectDiffuse *= sheenEnergyComp;
	#endif
	reflectedLight.indirectSpecular += indirectSpecular;
	reflectedLight.indirectDiffuse += indirectDiffuse;
}
#define RE_Direct				RE_Direct_Physical
#define RE_Direct_RectArea		RE_Direct_RectArea_Physical
#define RE_IndirectDiffuse		RE_IndirectDiffuse_Physical
#define RE_IndirectSpecular		RE_IndirectSpecular_Physical
float computeSpecularOcclusion( const in float dotNV, const in float ambientOcclusion, const in float roughness ) {
	return saturate( pow( dotNV + ambientOcclusion, exp2( - 16.0 * roughness - 1.0 ) ) - 1.0 + ambientOcclusion );
}`,lights_fragment_begin:`
vec3 geometryPosition = - vViewPosition;
vec3 geometryNormal = normal;
vec3 geometryViewDir = ( isOrthographic ) ? vec3( 0, 0, 1 ) : normalize( vViewPosition );
vec3 geometryClearcoatNormal = vec3( 0.0 );
#ifdef USE_CLEARCOAT
	geometryClearcoatNormal = clearcoatNormal;
#endif
#ifdef USE_IRIDESCENCE
	float dotNVi = saturate( dot( normal, geometryViewDir ) );
	if ( material.iridescenceThickness == 0.0 ) {
		material.iridescence = 0.0;
	} else {
		material.iridescence = saturate( material.iridescence );
	}
	if ( material.iridescence > 0.0 ) {
		vec3 iridescenceFresnelDielectric = evalIridescence( 1.0, material.iridescenceIOR, dotNVi, material.iridescenceThickness, material.specularColor );
		vec3 iridescenceFresnelMetallic = evalIridescence( 1.0, material.iridescenceIOR, dotNVi, material.iridescenceThickness, material.diffuseColor );
		material.iridescenceFresnel = mix( iridescenceFresnelDielectric, iridescenceFresnelMetallic, material.metalness );
		material.iridescenceF0Dielectric = Schlick_to_F0( iridescenceFresnelDielectric, 1.0, dotNVi );
		material.iridescenceF0Metallic = Schlick_to_F0( iridescenceFresnelMetallic, 1.0, dotNVi );
	}
#endif
#ifdef STANDARD
	float dotNVms = saturate( dot( geometryNormal, geometryViewDir ) );
	material.dfg = texture2D( dfgLUT, vec2( material.roughness, dotNVms ) ).rg;
	#if ( NUM_SUN_LIGHTS > 0 || NUM_DIR_LIGHTS > 0 || NUM_POINT_LIGHTS > 0 || NUM_SPOT_LIGHTS > 0 )
		float EssMs = material.dfg.x + material.dfg.y;
		material.multiScatteringCompensation = 1.0 + material.specularColorBlended * ( 1.0 / EssMs - 1.0 );
	#endif
#endif
IncidentLight directLight;
#if ( NUM_POINT_LIGHTS > 0 ) && defined( RE_Direct )
	PointLight pointLight;
	#if defined( USE_SHADOWMAP ) && NUM_POINT_LIGHT_SHADOWS > 0
	PointLightShadow pointLightShadow;
	#endif
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_POINT_LIGHTS; i ++ ) {
		pointLight = pointLights[ i ];
		getPointLightInfo( pointLight, geometryPosition, directLight );
		#if defined( USE_SHADOWMAP ) && ( UNROLLED_LOOP_INDEX < NUM_POINT_LIGHT_SHADOWS ) && ( defined( SHADOWMAP_TYPE_PCF ) || defined( SHADOWMAP_TYPE_BASIC ) )
		pointLightShadow = pointLightShadows[ i ];
		directLight.color *= ( directLight.visible && receiveShadow ) ? getPointShadow( pointShadowMap[ i ], pointLightShadow.shadowMapSize, pointLightShadow.shadowIntensity, pointLightShadow.shadowBias, pointLightShadow.shadowRadius, vPointShadowCoord[ i ], pointLightShadow.shadowCameraNear, pointLightShadow.shadowCameraFar ) : 1.0;
		#endif
		RE_Direct( directLight, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
	}
	#pragma unroll_loop_end
#endif
#if ( NUM_SPOT_LIGHTS > 0 ) && defined( RE_Direct )
	SpotLight spotLight;
	vec4 spotColor;
	vec3 spotLightCoord;
	bool inSpotLightMap;
	#if defined( USE_SHADOWMAP ) && NUM_SPOT_LIGHT_SHADOWS > 0
	SpotLightShadow spotLightShadow;
	#endif
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_SPOT_LIGHTS; i ++ ) {
		spotLight = spotLights[ i ];
		getSpotLightInfo( spotLight, geometryPosition, directLight );
		#if ( UNROLLED_LOOP_INDEX < NUM_SPOT_LIGHT_SHADOWS_WITH_MAPS )
		#define SPOT_LIGHT_MAP_INDEX UNROLLED_LOOP_INDEX
		#elif ( UNROLLED_LOOP_INDEX < NUM_SPOT_LIGHT_SHADOWS )
		#define SPOT_LIGHT_MAP_INDEX NUM_SPOT_LIGHT_MAPS
		#else
		#define SPOT_LIGHT_MAP_INDEX ( UNROLLED_LOOP_INDEX - NUM_SPOT_LIGHT_SHADOWS + NUM_SPOT_LIGHT_SHADOWS_WITH_MAPS )
		#endif
		#if ( SPOT_LIGHT_MAP_INDEX < NUM_SPOT_LIGHT_MAPS )
			spotLightCoord = vSpotLightCoord[ i ].xyz / vSpotLightCoord[ i ].w;
			inSpotLightMap = all( lessThan( abs( spotLightCoord * 2. - 1. ), vec3( 1.0 ) ) );
			spotColor = texture2D( spotLightMap[ SPOT_LIGHT_MAP_INDEX ], spotLightCoord.xy );
			directLight.color = inSpotLightMap ? directLight.color * spotColor.rgb : directLight.color;
		#endif
		#undef SPOT_LIGHT_MAP_INDEX
		#if defined( USE_SHADOWMAP ) && ( UNROLLED_LOOP_INDEX < NUM_SPOT_LIGHT_SHADOWS )
		spotLightShadow = spotLightShadows[ i ];
		directLight.color *= ( directLight.visible && receiveShadow ) ? getShadow( spotShadowMap[ i ], spotLightShadow.shadowMapSize, spotLightShadow.shadowIntensity, spotLightShadow.shadowBias, spotLightShadow.shadowRadius, vSpotLightCoord[ i ] ) : 1.0;
		#endif
		RE_Direct( directLight, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
	}
	#pragma unroll_loop_end
#endif
#if ( NUM_SUN_LIGHTS > 0 ) && defined( RE_Direct )
	SunLight sunLight;
	#if defined( USE_SHADOWMAP ) && NUM_SUN_LIGHT_SHADOWS > 0
	SunLightShadow sunLightShadow;
	#endif
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_SUN_LIGHTS; i ++ ) {
		sunLight = sunLights[ i ];
		getSunLightInfo( sunLight, directLight );
		#if defined( USE_SHADOWMAP ) && ( UNROLLED_LOOP_INDEX < NUM_SUN_LIGHT_SHADOWS )
		sunLightShadow = sunLightShadows[ i ];
		directLight.color *= ( directLight.visible && receiveShadow ) ? getSunShadow( sunShadowMap[ i ], sunLightShadow, UNROLLED_LOOP_INDEX ) : 1.0;
		#endif
		RE_Direct( directLight, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
	}
	#pragma unroll_loop_end
#endif
#if ( NUM_DIR_LIGHTS > 0 ) && defined( RE_Direct )
	DirectionalLight directionalLight;
	#if defined( USE_SHADOWMAP ) && NUM_DIR_LIGHT_SHADOWS > 0
	DirectionalLightShadow directionalLightShadow;
	#endif
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_DIR_LIGHTS; i ++ ) {
		directionalLight = directionalLights[ i ];
		getDirectionalLightInfo( directionalLight, directLight );
		#if defined( USE_SHADOWMAP ) && ( UNROLLED_LOOP_INDEX < NUM_DIR_LIGHT_SHADOWS )
		directionalLightShadow = directionalLightShadows[ i ];
		directLight.color *= ( directLight.visible && receiveShadow ) ? getShadow( directionalShadowMap[ i ], directionalLightShadow.shadowMapSize, directionalLightShadow.shadowIntensity, directionalLightShadow.shadowBias, directionalLightShadow.shadowRadius, vDirectionalShadowCoord[ i ] ) : 1.0;
		#endif
		RE_Direct( directLight, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
	}
	#pragma unroll_loop_end
#endif
#if ( NUM_RECT_AREA_LIGHTS > 0 ) && defined( RE_Direct_RectArea )
	RectAreaLight rectAreaLight;
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_RECT_AREA_LIGHTS; i ++ ) {
		rectAreaLight = rectAreaLights[ i ];
		RE_Direct_RectArea( rectAreaLight, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
	}
	#pragma unroll_loop_end
#endif
#if defined( RE_IndirectDiffuse )
	vec3 iblIrradiance = vec3( 0.0 );
	vec3 irradiance = getAmbientLightIrradiance( ambientLightColor );
	#if defined( USE_LIGHT_PROBES )
		irradiance += getLightProbeIrradiance( lightProbe, geometryNormal );
	#endif
	#if ( NUM_HEMI_LIGHTS > 0 )
		#pragma unroll_loop_start
		for ( int i = 0; i < NUM_HEMI_LIGHTS; i ++ ) {
			irradiance += getHemisphereLightIrradiance( hemisphereLights[ i ], geometryNormal );
		}
		#pragma unroll_loop_end
	#endif
	#ifdef USE_LIGHT_PROBES_GRID
		vec3 probeWorldPos = ( ( vec4( geometryPosition, 1.0 ) - viewMatrix[ 3 ] ) * viewMatrix ).xyz;
		vec3 probeWorldNormal = transformNormalByInverseViewMatrix( geometryNormal, viewMatrix );
		irradiance += getLightProbeGridIrradiance( probeWorldPos, probeWorldNormal );
	#endif
#endif
#if defined( RE_IndirectSpecular )
	vec3 radiance = vec3( 0.0 );
	vec3 clearcoatRadiance = vec3( 0.0 );
#endif`,lights_fragment_maps:`#if defined( RE_IndirectDiffuse )
	#ifdef USE_LIGHTMAP
		vec4 lightMapTexel = texture2D( lightMap, vLightMapUv );
		vec3 lightMapIrradiance = lightMapTexel.rgb * lightMapIntensity;
		irradiance += lightMapIrradiance;
	#endif
	#if defined( USE_ENVMAP ) && defined( ENVMAP_TYPE_CUBE_UV )
		#if defined( STANDARD ) || defined( LAMBERT ) || defined( PHONG )
			iblIrradiance += getIBLIrradiance( geometryNormal );
		#endif
	#endif
#endif
#if defined( USE_ENVMAP ) && defined( RE_IndirectSpecular )
	#ifdef USE_ANISOTROPY
		vec3 iblRadiance = getIBLAnisotropyRadiance( geometryViewDir, geometryNormal, material.roughness, material.anisotropyB, material.anisotropy );
	#else
		vec3 iblRadiance = getIBLRadiance( geometryViewDir, geometryNormal, material.roughness );
	#endif
	#ifdef USE_RETROREFLECTION
		#ifdef USE_ANISOTROPY
			vec3 retroIBLRadiance = getIBLAnisotropyRetroRadiance( geometryViewDir, geometryNormal, material.roughness, material.anisotropyB, material.anisotropy );
		#else
			vec3 retroIBLRadiance = getIBLRetroRadiance( geometryViewDir, geometryNormal, material.roughness );
		#endif
		iblRadiance = mix( iblRadiance, retroIBLRadiance, saturate( material.retroreflectivity ) );
	#endif
	radiance += iblRadiance;
	#ifdef USE_CLEARCOAT
		clearcoatRadiance += getIBLRadiance( geometryViewDir, geometryClearcoatNormal, material.clearcoatRoughness );
	#endif
#endif`,lights_fragment_end:`#if defined( RE_IndirectDiffuse )
	#if defined( LAMBERT ) || defined( PHONG )
		irradiance += iblIrradiance;
	#endif
	RE_IndirectDiffuse( irradiance, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
#endif
#if defined( RE_IndirectSpecular )
	RE_IndirectSpecular( radiance, iblIrradiance, clearcoatRadiance, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
#endif`,lightprobes_pars_fragment:`#ifdef USE_LIGHT_PROBES_GRID
uniform highp sampler3D probesSH;
uniform vec3 probesMin;
uniform vec3 probesMax;
uniform vec3 probesResolution;
vec3 getLightProbeGridIrradiance( vec3 worldPos, vec3 worldNormal ) {
	vec3 res = probesResolution;
	vec3 gridRange = probesMax - probesMin;
	vec3 resMinusOne = res - 1.0;
	vec3 probeSpacing = gridRange / resMinusOne;
	vec3 samplePos = worldPos + worldNormal * probeSpacing * 0.5;
	vec3 uvw = clamp( ( samplePos - probesMin ) / gridRange, 0.0, 1.0 );
	uvw = uvw * resMinusOne / res + 0.5 / res;
	float nz          = res.z;
	float paddedSlices = nz + 2.0;
	float atlasDepth  = 7.0 * paddedSlices;
	float uvZBase     = uvw.z * nz + 1.0;
	vec4 s0 = texture( probesSH, vec3( uvw.xy, ( uvZBase                       ) / atlasDepth ) );
	vec4 s1 = texture( probesSH, vec3( uvw.xy, ( uvZBase +       paddedSlices   ) / atlasDepth ) );
	vec4 s2 = texture( probesSH, vec3( uvw.xy, ( uvZBase + 2.0 * paddedSlices   ) / atlasDepth ) );
	vec4 s3 = texture( probesSH, vec3( uvw.xy, ( uvZBase + 3.0 * paddedSlices   ) / atlasDepth ) );
	vec4 s4 = texture( probesSH, vec3( uvw.xy, ( uvZBase + 4.0 * paddedSlices   ) / atlasDepth ) );
	vec4 s5 = texture( probesSH, vec3( uvw.xy, ( uvZBase + 5.0 * paddedSlices   ) / atlasDepth ) );
	vec4 s6 = texture( probesSH, vec3( uvw.xy, ( uvZBase + 6.0 * paddedSlices   ) / atlasDepth ) );
	vec3 c0 = s0.xyz;
	vec3 c1 = vec3( s0.w, s1.xy );
	vec3 c2 = vec3( s1.zw, s2.x );
	vec3 c3 = s2.yzw;
	vec3 c4 = s3.xyz;
	vec3 c5 = vec3( s3.w, s4.xy );
	vec3 c6 = vec3( s4.zw, s5.x );
	vec3 c7 = s5.yzw;
	vec3 c8 = s6.xyz;
	float x = worldNormal.x, y = worldNormal.y, z = worldNormal.z;
	vec3 result = c0 * 0.886227;
	result += c1 * 2.0 * 0.511664 * y;
	result += c2 * 2.0 * 0.511664 * z;
	result += c3 * 2.0 * 0.511664 * x;
	result += c4 * 2.0 * 0.429043 * x * y;
	result += c5 * 2.0 * 0.429043 * y * z;
	result += c6 * ( 0.743125 * z * z - 0.247708 );
	result += c7 * 2.0 * 0.429043 * x * z;
	result += c8 * 0.429043 * ( x * x - y * y );
	return max( result, vec3( 0.0 ) );
}
#endif`,logdepthbuf_fragment:`#if defined( USE_LOGARITHMIC_DEPTH_BUFFER )
	gl_FragDepth = vIsPerspective == 0.0 ? gl_FragCoord.z : log2( vFragDepth ) * logDepthBufFC * 0.5;
#endif`,logdepthbuf_pars_fragment:`#if defined( USE_LOGARITHMIC_DEPTH_BUFFER )
	uniform float logDepthBufFC;
	varying float vFragDepth;
	varying float vIsPerspective;
#endif`,logdepthbuf_pars_vertex:`#ifdef USE_LOGARITHMIC_DEPTH_BUFFER
	varying float vFragDepth;
	varying float vIsPerspective;
#endif`,logdepthbuf_vertex:`#ifdef USE_LOGARITHMIC_DEPTH_BUFFER
	vFragDepth = 1.0 + gl_Position.w;
	vIsPerspective = float( isPerspectiveMatrix( projectionMatrix ) );
#endif`,map_fragment:`#ifdef USE_MAP
	vec4 sampledDiffuseColor = texture2D( map, vMapUv );
	#ifdef DECODE_VIDEO_TEXTURE
		sampledDiffuseColor = sRGBTransferEOTF( sampledDiffuseColor );
	#endif
	diffuseColor *= sampledDiffuseColor;
#endif`,map_pars_fragment:`#ifdef USE_MAP
	uniform sampler2D map;
#endif`,map_particle_fragment:`#if defined( USE_MAP ) || defined( USE_ALPHAMAP )
	#if defined( USE_POINTS_UV )
		vec2 uv = vUv;
	#else
		vec2 uv = ( uvTransform * vec3( gl_PointCoord.x, 1.0 - gl_PointCoord.y, 1 ) ).xy;
	#endif
#endif
#ifdef USE_MAP
	diffuseColor *= texture2D( map, uv );
#endif
#ifdef USE_ALPHAMAP
	diffuseColor.a *= texture2D( alphaMap, uv ).g;
#endif`,map_particle_pars_fragment:`#if defined( USE_POINTS_UV )
	varying vec2 vUv;
#else
	#if defined( USE_MAP ) || defined( USE_ALPHAMAP )
		uniform mat3 uvTransform;
	#endif
#endif
#ifdef USE_MAP
	uniform sampler2D map;
#endif
#ifdef USE_ALPHAMAP
	uniform sampler2D alphaMap;
#endif`,metalnessmap_fragment:`float metalnessFactor = metalness;
#ifdef USE_METALNESSMAP
	vec4 texelMetalness = texture2D( metalnessMap, vMetalnessMapUv );
	metalnessFactor *= texelMetalness.b;
#endif`,metalnessmap_pars_fragment:`#ifdef USE_METALNESSMAP
	uniform sampler2D metalnessMap;
#endif`,morphinstance_vertex:`#ifdef USE_INSTANCING_MORPH
	float morphTargetInfluences[ MORPHTARGETS_COUNT ];
	float morphTargetBaseInfluence = texelFetch( morphTexture, ivec2( 0, gl_InstanceID ), 0 ).r;
	for ( int i = 0; i < MORPHTARGETS_COUNT; i ++ ) {
		morphTargetInfluences[i] =  texelFetch( morphTexture, ivec2( i + 1, gl_InstanceID ), 0 ).r;
	}
#endif`,morphcolor_vertex:`#if defined( USE_MORPHCOLORS )
	vColor *= morphTargetBaseInfluence;
	for ( int i = 0; i < MORPHTARGETS_COUNT; i ++ ) {
		#if defined( USE_COLOR_ALPHA )
			if ( morphTargetInfluences[ i ] != 0.0 ) vColor += getMorph( gl_VertexID, i, 2 ) * morphTargetInfluences[ i ];
		#elif defined( USE_COLOR )
			if ( morphTargetInfluences[ i ] != 0.0 ) vColor += getMorph( gl_VertexID, i, 2 ).rgb * morphTargetInfluences[ i ];
		#endif
	}
#endif`,morphnormal_vertex:`#ifdef USE_MORPHNORMALS
	objectNormal *= morphTargetBaseInfluence;
	for ( int i = 0; i < MORPHTARGETS_COUNT; i ++ ) {
		if ( morphTargetInfluences[ i ] != 0.0 ) objectNormal += getMorph( gl_VertexID, i, 1 ).xyz * morphTargetInfluences[ i ];
	}
#endif`,morphtarget_pars_vertex:`#ifdef USE_MORPHTARGETS
	#ifndef USE_INSTANCING_MORPH
		uniform float morphTargetBaseInfluence;
		uniform float morphTargetInfluences[ MORPHTARGETS_COUNT ];
	#endif
	uniform sampler2DArray morphTargetsTexture;
	uniform ivec2 morphTargetsTextureSize;
	vec4 getMorph( const in int vertexIndex, const in int morphTargetIndex, const in int offset ) {
		int texelIndex = vertexIndex * MORPHTARGETS_TEXTURE_STRIDE + offset;
		int y = texelIndex / morphTargetsTextureSize.x;
		int x = texelIndex - y * morphTargetsTextureSize.x;
		ivec3 morphUV = ivec3( x, y, morphTargetIndex );
		return texelFetch( morphTargetsTexture, morphUV, 0 );
	}
#endif`,morphtarget_vertex:`#ifdef USE_MORPHTARGETS
	transformed *= morphTargetBaseInfluence;
	for ( int i = 0; i < MORPHTARGETS_COUNT; i ++ ) {
		if ( morphTargetInfluences[ i ] != 0.0 ) transformed += getMorph( gl_VertexID, i, 0 ).xyz * morphTargetInfluences[ i ];
	}
#endif`,normal_fragment_begin:`float faceDirection = gl_FrontFacing ? 1.0 : - 1.0;
#ifdef FLAT_SHADED
	vec3 fdx = dFdx( vViewPosition );
	vec3 fdy = dFdy( vViewPosition );
	vec3 normal = normalize( cross( fdx, fdy ) );
#else
	vec3 normal = normalize( vNormal );
	#ifdef DOUBLE_SIDED
		normal *= faceDirection;
	#endif
#endif
#if defined( USE_NORMALMAP_TANGENTSPACE ) || defined( USE_CLEARCOAT_NORMALMAP ) || defined( USE_ANISOTROPY )
	#ifdef USE_TANGENT
		mat3 tbn = mat3( normalize( vTangent ), normalize( vBitangent ), normal );
	#else
		mat3 tbn = getTangentFrame( - vViewPosition, normal,
		#if defined( USE_NORMALMAP )
			vNormalMapUv
		#elif defined( USE_CLEARCOAT_NORMALMAP )
			vClearcoatNormalMapUv
		#else
			vUv
		#endif
		);
	#endif
	#ifdef DOUBLE_SIDED
		tbn[0] *= faceDirection;
		tbn[1] *= faceDirection;
	#endif
#endif
#ifdef USE_CLEARCOAT_NORMALMAP
	#ifdef USE_TANGENT
		mat3 tbn2 = mat3( normalize( vTangent ), normalize( vBitangent ), normal );
	#else
		mat3 tbn2 = getTangentFrame( - vViewPosition, normal, vClearcoatNormalMapUv );
	#endif
	#ifdef DOUBLE_SIDED
		tbn2[0] *= faceDirection;
		tbn2[1] *= faceDirection;
	#endif
#endif
vec3 nonPerturbedNormal = normal;`,normal_fragment_maps:`#ifdef USE_NORMALMAP_OBJECTSPACE
	normal = texture2D( normalMap, vNormalMapUv ).xyz * 2.0 - 1.0;
	#ifdef FLIP_SIDED
		normal = - normal;
	#endif
	#ifdef DOUBLE_SIDED
		normal = normal * faceDirection;
	#endif
	normal = normalize( normalMatrix * normal );
#elif defined( USE_NORMALMAP_TANGENTSPACE )
	vec3 mapN = texture2D( normalMap, vNormalMapUv ).xyz * 2.0 - 1.0;
	#if defined( USE_PACKED_NORMALMAP )
		mapN = vec3( mapN.xy, sqrt( saturate( 1.0 - dot( mapN.xy, mapN.xy ) ) ) );
	#endif
	mapN.xy *= normalScale;
	normal = normalize( tbn * mapN );
#elif defined( USE_BUMPMAP )
	normal = perturbNormalArb( - vViewPosition, normal, dHdxy_fwd(), faceDirection );
#endif`,normal_pars_fragment:`#ifndef FLAT_SHADED
	varying vec3 vNormal;
	#ifdef USE_TANGENT
		varying vec3 vTangent;
		varying vec3 vBitangent;
	#endif
#endif`,normal_pars_vertex:`#ifndef FLAT_SHADED
	varying vec3 vNormal;
	#ifdef USE_TANGENT
		varying vec3 vTangent;
		varying vec3 vBitangent;
	#endif
#endif`,normal_vertex:`#ifndef FLAT_SHADED
	vNormal = normalize( transformedNormal );
	#ifdef USE_TANGENT
		vTangent = normalize( transformedTangent );
		vBitangent = normalize( cross( vNormal, vTangent ) * tangent.w );
		#ifdef FLIP_SIDED
			vBitangent = - vBitangent;
		#endif
	#endif
#endif`,normalmap_pars_fragment:`#ifdef USE_NORMALMAP
	uniform sampler2D normalMap;
	uniform vec2 normalScale;
#endif
#ifdef USE_NORMALMAP_OBJECTSPACE
	uniform mat3 normalMatrix;
#endif
#if ! defined ( USE_TANGENT ) && ( defined ( USE_NORMALMAP_TANGENTSPACE ) || defined ( USE_CLEARCOAT_NORMALMAP ) || defined( USE_ANISOTROPY ) )
	mat3 getTangentFrame( vec3 eye_pos, vec3 surf_norm, vec2 uv ) {
		vec3 q0 = dFdx( eye_pos.xyz );
		vec3 q1 = dFdy( eye_pos.xyz );
		vec2 st0 = dFdx( uv.st );
		vec2 st1 = dFdy( uv.st );
		vec3 N = surf_norm;
		vec3 q1perp = cross( q1, N );
		vec3 q0perp = cross( N, q0 );
		vec3 T = q1perp * st0.x + q0perp * st1.x;
		vec3 B = q1perp * st0.y + q0perp * st1.y;
		float det = max( dot( T, T ), dot( B, B ) );
		float scale = ( det == 0.0 ) ? 0.0 : inversesqrt( det );
		return mat3( T * scale, B * scale, N );
	}
#endif`,clearcoat_normal_fragment_begin:`#ifdef USE_CLEARCOAT
	vec3 clearcoatNormal = nonPerturbedNormal;
#endif`,clearcoat_normal_fragment_maps:`#ifdef USE_CLEARCOAT_NORMALMAP
	vec3 clearcoatMapN = texture2D( clearcoatNormalMap, vClearcoatNormalMapUv ).xyz * 2.0 - 1.0;
	clearcoatMapN.xy *= clearcoatNormalScale;
	clearcoatNormal = normalize( tbn2 * clearcoatMapN );
#endif`,clearcoat_pars_fragment:`#ifdef USE_CLEARCOATMAP
	uniform sampler2D clearcoatMap;
#endif
#ifdef USE_CLEARCOAT_NORMALMAP
	uniform sampler2D clearcoatNormalMap;
	uniform vec2 clearcoatNormalScale;
#endif
#ifdef USE_CLEARCOAT_ROUGHNESSMAP
	uniform sampler2D clearcoatRoughnessMap;
#endif`,iridescence_pars_fragment:`#ifdef USE_IRIDESCENCEMAP
	uniform sampler2D iridescenceMap;
#endif
#ifdef USE_IRIDESCENCE_THICKNESSMAP
	uniform sampler2D iridescenceThicknessMap;
#endif`,opaque_fragment:`#ifdef OPAQUE
diffuseColor.a = 1.0;
#endif
#ifdef USE_TRANSMISSION
diffuseColor.a *= material.transmissionAlpha;
#endif
gl_FragColor = vec4( outgoingLight, diffuseColor.a );`,packing:`vec3 packNormalToRGB( const in vec3 normal ) {
	return normalize( normal ) * 0.5 + 0.5;
}
vec3 unpackRGBToNormal( const in vec3 rgb ) {
	return 2.0 * rgb.xyz - 1.0;
}
const float PackUpscale = 256. / 255.;const float UnpackDownscale = 255. / 256.;const float ShiftRight8 = 1. / 256.;
const float Inv255 = 1. / 255.;
const vec4 PackFactors = vec4( 1.0, 256.0, 256.0 * 256.0, 256.0 * 256.0 * 256.0 );
const vec2 UnpackFactors2 = vec2( UnpackDownscale, 1.0 / PackFactors.g );
const vec3 UnpackFactors3 = vec3( UnpackDownscale / PackFactors.rg, 1.0 / PackFactors.b );
const vec4 UnpackFactors4 = vec4( UnpackDownscale / PackFactors.rgb, 1.0 / PackFactors.a );
vec4 packDepthToRGBA( const in float v ) {
	if( v <= 0.0 )
		return vec4( 0., 0., 0., 0. );
	if( v >= 1.0 )
		return vec4( 1., 1., 1., 1. );
	float vuf;
	float af = modf( v * PackFactors.a, vuf );
	float bf = modf( vuf * ShiftRight8, vuf );
	float gf = modf( vuf * ShiftRight8, vuf );
	return vec4( vuf * Inv255, gf * PackUpscale, bf * PackUpscale, af );
}
vec3 packDepthToRGB( const in float v ) {
	if( v <= 0.0 )
		return vec3( 0., 0., 0. );
	if( v >= 1.0 )
		return vec3( 1., 1., 1. );
	float vuf;
	float bf = modf( v * PackFactors.b, vuf );
	float gf = modf( vuf * ShiftRight8, vuf );
	return vec3( vuf * Inv255, gf * PackUpscale, bf );
}
vec2 packDepthToRG( const in float v ) {
	if( v <= 0.0 )
		return vec2( 0., 0. );
	if( v >= 1.0 )
		return vec2( 1., 1. );
	float vuf;
	float gf = modf( v * 256., vuf );
	return vec2( vuf * Inv255, gf );
}
float unpackRGBAToDepth( const in vec4 v ) {
	return dot( v, UnpackFactors4 );
}
float unpackRGBToDepth( const in vec3 v ) {
	return dot( v, UnpackFactors3 );
}
float unpackRGToDepth( const in vec2 v ) {
	return v.r * UnpackFactors2.r + v.g * UnpackFactors2.g;
}
vec4 pack2HalfToRGBA( const in vec2 v ) {
	vec4 r = vec4( v.x, fract( v.x * 255.0 ), v.y, fract( v.y * 255.0 ) );
	return vec4( r.x - r.y / 255.0, r.y, r.z - r.w / 255.0, r.w );
}
vec2 unpackRGBATo2Half( const in vec4 v ) {
	return vec2( v.x + ( v.y / 255.0 ), v.z + ( v.w / 255.0 ) );
}
float viewZToOrthographicDepth( const in float viewZ, const in float near, const in float far ) {
	return ( viewZ + near ) / ( near - far );
}
float orthographicDepthToViewZ( const in float depth, const in float near, const in float far ) {
	#ifdef USE_REVERSED_DEPTH_BUFFER
	
		return depth * ( far - near ) - far;
	#else
		return depth * ( near - far ) - near;
	#endif
}
float viewZToPerspectiveDepth( const in float viewZ, const in float near, const in float far ) {
	return ( ( near + viewZ ) * far ) / ( ( far - near ) * viewZ );
}
float perspectiveDepthToViewZ( const in float depth, const in float near, const in float far ) {
	
	#ifdef USE_REVERSED_DEPTH_BUFFER
		return ( near * far ) / ( ( near - far ) * depth - near );
	#else
		return ( near * far ) / ( ( far - near ) * depth - far );
	#endif
}`,premultiplied_alpha_fragment:`#ifdef PREMULTIPLIED_ALPHA
	gl_FragColor.rgb *= gl_FragColor.a;
#endif`,project_vertex:`vec4 mvPosition = vec4( transformed, 1.0 );
#ifdef USE_BATCHING
	mvPosition = batchingMatrix * mvPosition;
#endif
#ifdef USE_INSTANCING
	mvPosition = instanceMatrix * mvPosition;
#endif
mvPosition = modelViewMatrix * mvPosition;
gl_Position = projectionMatrix * mvPosition;`,dithering_fragment:`#ifdef DITHERING
	gl_FragColor.rgb = dithering( gl_FragColor.rgb );
#endif`,dithering_pars_fragment:`#ifdef DITHERING
	vec3 dithering( vec3 color ) {
		float grid_position = rand( gl_FragCoord.xy );
		vec3 dither_shift_RGB = vec3( 0.25 / 255.0, -0.25 / 255.0, 0.25 / 255.0 );
		dither_shift_RGB = mix( 2.0 * dither_shift_RGB, -2.0 * dither_shift_RGB, grid_position );
		return color + dither_shift_RGB;
	}
#endif`,roughnessmap_fragment:`float roughnessFactor = roughness;
#ifdef USE_ROUGHNESSMAP
	vec4 texelRoughness = texture2D( roughnessMap, vRoughnessMapUv );
	roughnessFactor *= texelRoughness.g;
#endif`,roughnessmap_pars_fragment:`#ifdef USE_ROUGHNESSMAP
	uniform sampler2D roughnessMap;
#endif`,shadowmap_pars_fragment:`#if NUM_SPOT_LIGHT_COORDS > 0
	varying vec4 vSpotLightCoord[ NUM_SPOT_LIGHT_COORDS ];
#endif
#if NUM_SPOT_LIGHT_MAPS > 0
	uniform sampler2D spotLightMap[ NUM_SPOT_LIGHT_MAPS ];
#endif
#ifdef USE_SHADOWMAP
	#if NUM_SUN_LIGHT_SHADOWS > 0
		#define SUN_LIGHT_CASCADES 2
		#if defined( SHADOWMAP_TYPE_PCF )
			uniform sampler2DShadow sunShadowMap[ NUM_SUN_LIGHT_SHADOWS ];
		#else
			uniform sampler2D sunShadowMap[ NUM_SUN_LIGHT_SHADOWS ];
		#endif
		uniform mat4 sunShadowMatrix[ NUM_SUN_LIGHT_SHADOWS * SUN_LIGHT_CASCADES ];
		uniform vec4 sunShadowCascade[ NUM_SUN_LIGHT_SHADOWS * SUN_LIGHT_CASCADES ];
		varying vec4 vSunShadowWorldPosition;
		varying vec3 vSunShadowWorldNormal;
		struct SunLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
		};
		uniform SunLightShadow sunLightShadows[ NUM_SUN_LIGHT_SHADOWS ];
	#endif
	#if NUM_DIR_LIGHT_SHADOWS > 0
		#if defined( SHADOWMAP_TYPE_PCF )
			uniform sampler2DShadow directionalShadowMap[ NUM_DIR_LIGHT_SHADOWS ];
		#else
			uniform sampler2D directionalShadowMap[ NUM_DIR_LIGHT_SHADOWS ];
		#endif
		varying vec4 vDirectionalShadowCoord[ NUM_DIR_LIGHT_SHADOWS ];
		struct DirectionalLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
		};
		uniform DirectionalLightShadow directionalLightShadows[ NUM_DIR_LIGHT_SHADOWS ];
	#endif
	#if NUM_SPOT_LIGHT_SHADOWS > 0
		#if defined( SHADOWMAP_TYPE_PCF )
			uniform sampler2DShadow spotShadowMap[ NUM_SPOT_LIGHT_SHADOWS ];
		#else
			uniform sampler2D spotShadowMap[ NUM_SPOT_LIGHT_SHADOWS ];
		#endif
		struct SpotLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
		};
		uniform SpotLightShadow spotLightShadows[ NUM_SPOT_LIGHT_SHADOWS ];
	#endif
	#if NUM_POINT_LIGHT_SHADOWS > 0
		#if defined( SHADOWMAP_TYPE_PCF )
			uniform samplerCubeShadow pointShadowMap[ NUM_POINT_LIGHT_SHADOWS ];
		#elif defined( SHADOWMAP_TYPE_BASIC )
			uniform samplerCube pointShadowMap[ NUM_POINT_LIGHT_SHADOWS ];
		#endif
		varying vec4 vPointShadowCoord[ NUM_POINT_LIGHT_SHADOWS ];
		struct PointLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
			float shadowCameraNear;
			float shadowCameraFar;
		};
		uniform PointLightShadow pointLightShadows[ NUM_POINT_LIGHT_SHADOWS ];
	#endif
	#if defined( SHADOWMAP_TYPE_PCF )
		float interleavedGradientNoise( vec2 position ) {
			return fract( 52.9829189 * fract( dot( position, vec2( 0.06711056, 0.00583715 ) ) ) );
		}
		vec2 vogelDiskSample( int sampleIndex, int samplesCount, float phi ) {
			const float goldenAngle = 2.399963229728653;
			float r = sqrt( ( float( sampleIndex ) + 0.5 ) / float( samplesCount ) );
			float theta = float( sampleIndex ) * goldenAngle + phi;
			return vec2( cos( theta ), sin( theta ) ) * r;
		}
	#endif
	#if defined( SHADOWMAP_TYPE_PCF )
		float getShadow( sampler2DShadow shadowMap, vec2 shadowMapSize, float shadowIntensity, float shadowBias, float shadowRadius, vec4 shadowCoord ) {
			float shadow = 1.0;
			shadowCoord.xyz /= shadowCoord.w;
			shadowCoord.z += shadowBias;
			bool inFrustum = shadowCoord.x >= 0.0 && shadowCoord.x <= 1.0 && shadowCoord.y >= 0.0 && shadowCoord.y <= 1.0;
			bool frustumTest = inFrustum && shadowCoord.z <= 1.0;
			if ( frustumTest ) {
				vec2 texelSize = vec2( 1.0 ) / shadowMapSize;
				float radius = shadowRadius * texelSize.x;
				float phi = interleavedGradientNoise( gl_FragCoord.xy ) * PI2;
				shadow = (
					texture( shadowMap, vec3( shadowCoord.xy + vogelDiskSample( 0, 5, phi ) * radius, shadowCoord.z ) ) +
					texture( shadowMap, vec3( shadowCoord.xy + vogelDiskSample( 1, 5, phi ) * radius, shadowCoord.z ) ) +
					texture( shadowMap, vec3( shadowCoord.xy + vogelDiskSample( 2, 5, phi ) * radius, shadowCoord.z ) ) +
					texture( shadowMap, vec3( shadowCoord.xy + vogelDiskSample( 3, 5, phi ) * radius, shadowCoord.z ) ) +
					texture( shadowMap, vec3( shadowCoord.xy + vogelDiskSample( 4, 5, phi ) * radius, shadowCoord.z ) )
				) * 0.2;
			}
			return mix( 1.0, shadow, shadowIntensity );
		}
	#elif defined( SHADOWMAP_TYPE_VSM )
		float getShadow( sampler2D shadowMap, vec2 shadowMapSize, float shadowIntensity, float shadowBias, float shadowRadius, vec4 shadowCoord ) {
			float shadow = 1.0;
			shadowCoord.xyz /= shadowCoord.w;
			#ifdef USE_REVERSED_DEPTH_BUFFER
				shadowCoord.z -= shadowBias;
			#else
				shadowCoord.z += shadowBias;
			#endif
			bool inFrustum = shadowCoord.x >= 0.0 && shadowCoord.x <= 1.0 && shadowCoord.y >= 0.0 && shadowCoord.y <= 1.0;
			bool frustumTest = inFrustum && shadowCoord.z <= 1.0;
			if ( frustumTest ) {
				vec2 distribution = texture2D( shadowMap, shadowCoord.xy ).rg;
				float mean = distribution.x;
				float variance = distribution.y * distribution.y;
				#ifdef USE_REVERSED_DEPTH_BUFFER
					float hard_shadow = step( mean, shadowCoord.z );
				#else
					float hard_shadow = step( shadowCoord.z, mean );
				#endif
				
				if ( hard_shadow == 1.0 ) {
					shadow = 1.0;
				} else {
					variance = max( variance, 0.0000001 );
					float d = shadowCoord.z - mean;
					float p_max = variance / ( variance + d * d );
					p_max = clamp( ( p_max - 0.3 ) / 0.65, 0.0, 1.0 );
					shadow = max( hard_shadow, p_max );
				}
			}
			return mix( 1.0, shadow, shadowIntensity );
		}
	#else
		float getShadow( sampler2D shadowMap, vec2 shadowMapSize, float shadowIntensity, float shadowBias, float shadowRadius, vec4 shadowCoord ) {
			float shadow = 1.0;
			shadowCoord.xyz /= shadowCoord.w;
			#ifdef USE_REVERSED_DEPTH_BUFFER
				shadowCoord.z -= shadowBias;
			#else
				shadowCoord.z += shadowBias;
			#endif
			bool inFrustum = shadowCoord.x >= 0.0 && shadowCoord.x <= 1.0 && shadowCoord.y >= 0.0 && shadowCoord.y <= 1.0;
			bool frustumTest = inFrustum && shadowCoord.z <= 1.0;
			if ( frustumTest ) {
				float depth = texture2D( shadowMap, shadowCoord.xy ).r;
				#ifdef USE_REVERSED_DEPTH_BUFFER
					shadow = step( depth, shadowCoord.z );
				#else
					shadow = step( shadowCoord.z, depth );
				#endif
			}
			return mix( 1.0, shadow, shadowIntensity );
		}
	#endif
	#if NUM_SUN_LIGHT_SHADOWS > 0
		float getSunShadow(
			#if defined( SHADOWMAP_TYPE_PCF )
				sampler2DShadow shadowMap,
			#else
				sampler2D shadowMap,
			#endif
			SunLightShadow sunLightShadow,
			int shadowIndex
		) {
			vec4 shadowWorldPosition = vec4( vSunShadowWorldPosition.xyz + vSunShadowWorldNormal * sunLightShadow.shadowNormalBias, 1.0 );
			float viewDepth = vSunShadowWorldPosition.w;
			int cascadeOffset = shadowIndex * SUN_LIGHT_CASCADES;
			float shadow = 1.0;
			for ( int i = SUN_LIGHT_CASCADES - 1; i >= 0; i -- ) {
				vec4 cascade = sunShadowCascade[ cascadeOffset + i ];
				if ( viewDepth >= cascade.x && viewDepth < cascade.y ) {
					float cascadeShadow = getShadow(
						shadowMap,
						sunLightShadow.shadowMapSize,
						sunLightShadow.shadowIntensity,
						sunLightShadow.shadowBias,
						sunLightShadow.shadowRadius,
						sunShadowMatrix[ cascadeOffset + i ] * shadowWorldPosition
					);
					shadow = mix( cascadeShadow, shadow, smoothstep( cascade.z, cascade.y, viewDepth ) );
				}
			}
			return shadow;
		}
	#endif
	#if NUM_POINT_LIGHT_SHADOWS > 0
	#if defined( SHADOWMAP_TYPE_PCF )
	float getPointShadow( samplerCubeShadow shadowMap, vec2 shadowMapSize, float shadowIntensity, float shadowBias, float shadowRadius, vec4 shadowCoord, float shadowCameraNear, float shadowCameraFar ) {
		float shadow = 1.0;
		vec3 lightToPosition = shadowCoord.xyz;
		vec3 bd3D = normalize( lightToPosition );
		vec3 absVec = abs( lightToPosition );
		float viewSpaceZ = max( max( absVec.x, absVec.y ), absVec.z );
		if ( viewSpaceZ - shadowCameraFar <= 0.0 && viewSpaceZ - shadowCameraNear >= 0.0 ) {
			#ifdef USE_REVERSED_DEPTH_BUFFER
				float dp = ( shadowCameraNear * ( shadowCameraFar - viewSpaceZ ) ) / ( viewSpaceZ * ( shadowCameraFar - shadowCameraNear ) );
				dp -= shadowBias;
			#else
				float dp = ( shadowCameraFar * ( viewSpaceZ - shadowCameraNear ) ) / ( viewSpaceZ * ( shadowCameraFar - shadowCameraNear ) );
				dp += shadowBias;
			#endif
			float texelSize = shadowRadius / shadowMapSize.x;
			vec3 absDir = abs( bd3D );
			vec3 tangent = absDir.x > absDir.z ? vec3( 0.0, 1.0, 0.0 ) : vec3( 1.0, 0.0, 0.0 );
			tangent = normalize( cross( bd3D, tangent ) );
			vec3 bitangent = cross( bd3D, tangent );
			float phi = interleavedGradientNoise( gl_FragCoord.xy ) * PI2;
			vec2 sample0 = vogelDiskSample( 0, 5, phi );
			vec2 sample1 = vogelDiskSample( 1, 5, phi );
			vec2 sample2 = vogelDiskSample( 2, 5, phi );
			vec2 sample3 = vogelDiskSample( 3, 5, phi );
			vec2 sample4 = vogelDiskSample( 4, 5, phi );
			shadow = (
				texture( shadowMap, vec4( bd3D + ( tangent * sample0.x + bitangent * sample0.y ) * texelSize, dp ) ) +
				texture( shadowMap, vec4( bd3D + ( tangent * sample1.x + bitangent * sample1.y ) * texelSize, dp ) ) +
				texture( shadowMap, vec4( bd3D + ( tangent * sample2.x + bitangent * sample2.y ) * texelSize, dp ) ) +
				texture( shadowMap, vec4( bd3D + ( tangent * sample3.x + bitangent * sample3.y ) * texelSize, dp ) ) +
				texture( shadowMap, vec4( bd3D + ( tangent * sample4.x + bitangent * sample4.y ) * texelSize, dp ) )
			) * 0.2;
		}
		return mix( 1.0, shadow, shadowIntensity );
	}
	#elif defined( SHADOWMAP_TYPE_BASIC )
	float getPointShadow( samplerCube shadowMap, vec2 shadowMapSize, float shadowIntensity, float shadowBias, float shadowRadius, vec4 shadowCoord, float shadowCameraNear, float shadowCameraFar ) {
		float shadow = 1.0;
		vec3 lightToPosition = shadowCoord.xyz;
		vec3 absVec = abs( lightToPosition );
		float viewSpaceZ = max( max( absVec.x, absVec.y ), absVec.z );
		if ( viewSpaceZ - shadowCameraFar <= 0.0 && viewSpaceZ - shadowCameraNear >= 0.0 ) {
			float dp = ( shadowCameraFar * ( viewSpaceZ - shadowCameraNear ) ) / ( viewSpaceZ * ( shadowCameraFar - shadowCameraNear ) );
			dp += shadowBias;
			vec3 bd3D = normalize( lightToPosition );
			float depth = textureCube( shadowMap, bd3D ).r;
			#ifdef USE_REVERSED_DEPTH_BUFFER
				depth = 1.0 - depth;
			#endif
			shadow = step( dp, depth );
		}
		return mix( 1.0, shadow, shadowIntensity );
	}
	#endif
	#endif
#endif`,shadowmap_pars_vertex:`#if NUM_SPOT_LIGHT_COORDS > 0
	uniform mat4 spotLightMatrix[ NUM_SPOT_LIGHT_COORDS ];
	varying vec4 vSpotLightCoord[ NUM_SPOT_LIGHT_COORDS ];
#endif
#ifdef USE_SHADOWMAP
	#if NUM_SUN_LIGHT_SHADOWS > 0
		varying vec4 vSunShadowWorldPosition;
		varying vec3 vSunShadowWorldNormal;
	#endif
	#if NUM_DIR_LIGHT_SHADOWS > 0
		uniform mat4 directionalShadowMatrix[ NUM_DIR_LIGHT_SHADOWS ];
		varying vec4 vDirectionalShadowCoord[ NUM_DIR_LIGHT_SHADOWS ];
		struct DirectionalLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
		};
		uniform DirectionalLightShadow directionalLightShadows[ NUM_DIR_LIGHT_SHADOWS ];
	#endif
	#if NUM_SPOT_LIGHT_SHADOWS > 0
		struct SpotLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
		};
		uniform SpotLightShadow spotLightShadows[ NUM_SPOT_LIGHT_SHADOWS ];
	#endif
	#if NUM_POINT_LIGHT_SHADOWS > 0
		uniform mat4 pointShadowMatrix[ NUM_POINT_LIGHT_SHADOWS ];
		varying vec4 vPointShadowCoord[ NUM_POINT_LIGHT_SHADOWS ];
		struct PointLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
			float shadowCameraNear;
			float shadowCameraFar;
		};
		uniform PointLightShadow pointLightShadows[ NUM_POINT_LIGHT_SHADOWS ];
	#endif
#endif`,shadowmap_vertex:`#if ( defined( USE_SHADOWMAP ) && ( NUM_DIR_LIGHT_SHADOWS > 0 || NUM_SUN_LIGHT_SHADOWS > 0 || NUM_POINT_LIGHT_SHADOWS > 0 ) ) || ( NUM_SPOT_LIGHT_COORDS > 0 )
	#ifdef HAS_NORMAL
		vec3 shadowWorldNormal = transformNormalByInverseViewMatrix( transformedNormal, viewMatrix );
	#else
		vec3 shadowWorldNormal = vec3( 0.0 );
	#endif
	vec4 shadowWorldPosition;
#endif
#if defined( USE_SHADOWMAP )
	#if NUM_SUN_LIGHT_SHADOWS > 0
		vSunShadowWorldPosition = vec4( worldPosition.xyz, - mvPosition.z );
		vSunShadowWorldNormal = shadowWorldNormal;
	#endif
	#if NUM_DIR_LIGHT_SHADOWS > 0
		#pragma unroll_loop_start
		for ( int i = 0; i < NUM_DIR_LIGHT_SHADOWS; i ++ ) {
			shadowWorldPosition = worldPosition + vec4( shadowWorldNormal * directionalLightShadows[ i ].shadowNormalBias, 0 );
			vDirectionalShadowCoord[ i ] = directionalShadowMatrix[ i ] * shadowWorldPosition;
		}
		#pragma unroll_loop_end
	#endif
	#if NUM_POINT_LIGHT_SHADOWS > 0
		#pragma unroll_loop_start
		for ( int i = 0; i < NUM_POINT_LIGHT_SHADOWS; i ++ ) {
			shadowWorldPosition = worldPosition + vec4( shadowWorldNormal * pointLightShadows[ i ].shadowNormalBias, 0 );
			vPointShadowCoord[ i ] = pointShadowMatrix[ i ] * shadowWorldPosition;
		}
		#pragma unroll_loop_end
	#endif
#endif
#if NUM_SPOT_LIGHT_COORDS > 0
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_SPOT_LIGHT_COORDS; i ++ ) {
		shadowWorldPosition = worldPosition;
		#if ( defined( USE_SHADOWMAP ) && UNROLLED_LOOP_INDEX < NUM_SPOT_LIGHT_SHADOWS )
			shadowWorldPosition.xyz += shadowWorldNormal * spotLightShadows[ i ].shadowNormalBias;
		#endif
		vSpotLightCoord[ i ] = spotLightMatrix[ i ] * shadowWorldPosition;
	}
	#pragma unroll_loop_end
#endif`,shadowmask_pars_fragment:`float getShadowMask() {
	float shadow = 1.0;
	#ifdef USE_SHADOWMAP
	#if NUM_SUN_LIGHT_SHADOWS > 0
	SunLightShadow sunLight;
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_SUN_LIGHT_SHADOWS; i ++ ) {
		sunLight = sunLightShadows[ i ];
		shadow *= receiveShadow ? getSunShadow( sunShadowMap[ i ], sunLight, UNROLLED_LOOP_INDEX ) : 1.0;
	}
	#pragma unroll_loop_end
	#endif
	#if NUM_DIR_LIGHT_SHADOWS > 0
	DirectionalLightShadow directionalLight;
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_DIR_LIGHT_SHADOWS; i ++ ) {
		directionalLight = directionalLightShadows[ i ];
		shadow *= receiveShadow ? getShadow( directionalShadowMap[ i ], directionalLight.shadowMapSize, directionalLight.shadowIntensity, directionalLight.shadowBias, directionalLight.shadowRadius, vDirectionalShadowCoord[ i ] ) : 1.0;
	}
	#pragma unroll_loop_end
	#endif
	#if NUM_SPOT_LIGHT_SHADOWS > 0
	SpotLightShadow spotLight;
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_SPOT_LIGHT_SHADOWS; i ++ ) {
		spotLight = spotLightShadows[ i ];
		shadow *= receiveShadow ? getShadow( spotShadowMap[ i ], spotLight.shadowMapSize, spotLight.shadowIntensity, spotLight.shadowBias, spotLight.shadowRadius, vSpotLightCoord[ i ] ) : 1.0;
	}
	#pragma unroll_loop_end
	#endif
	#if NUM_POINT_LIGHT_SHADOWS > 0 && ( defined( SHADOWMAP_TYPE_PCF ) || defined( SHADOWMAP_TYPE_BASIC ) )
	PointLightShadow pointLight;
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_POINT_LIGHT_SHADOWS; i ++ ) {
		pointLight = pointLightShadows[ i ];
		shadow *= receiveShadow ? getPointShadow( pointShadowMap[ i ], pointLight.shadowMapSize, pointLight.shadowIntensity, pointLight.shadowBias, pointLight.shadowRadius, vPointShadowCoord[ i ], pointLight.shadowCameraNear, pointLight.shadowCameraFar ) : 1.0;
	}
	#pragma unroll_loop_end
	#endif
	#endif
	return shadow;
}`,skinbase_vertex:`#ifdef USE_SKINNING
	mat4 boneMatX = getBoneMatrix( skinIndex.x );
	mat4 boneMatY = getBoneMatrix( skinIndex.y );
	mat4 boneMatZ = getBoneMatrix( skinIndex.z );
	mat4 boneMatW = getBoneMatrix( skinIndex.w );
#endif`,skinning_pars_vertex:`#ifdef USE_SKINNING
	uniform mat4 bindMatrix;
	uniform mat4 bindMatrixInverse;
	uniform highp sampler2D boneTexture;
	mat4 getBoneMatrix( const in float i ) {
		int size = textureSize( boneTexture, 0 ).x;
		int j = int( i ) * 4;
		int x = j % size;
		int y = j / size;
		vec4 v1 = texelFetch( boneTexture, ivec2( x, y ), 0 );
		vec4 v2 = texelFetch( boneTexture, ivec2( x + 1, y ), 0 );
		vec4 v3 = texelFetch( boneTexture, ivec2( x + 2, y ), 0 );
		vec4 v4 = texelFetch( boneTexture, ivec2( x + 3, y ), 0 );
		return mat4( v1, v2, v3, v4 );
	}
#endif`,skinning_vertex:`#ifdef USE_SKINNING
	vec4 skinVertex = bindMatrix * vec4( transformed, 1.0 );
	vec4 skinned = vec4( 0.0 );
	skinned += boneMatX * skinVertex * skinWeight.x;
	skinned += boneMatY * skinVertex * skinWeight.y;
	skinned += boneMatZ * skinVertex * skinWeight.z;
	skinned += boneMatW * skinVertex * skinWeight.w;
	transformed = ( bindMatrixInverse * skinned ).xyz;
#endif`,skinnormal_vertex:`#ifdef USE_SKINNING
	mat4 skinMatrix = mat4( 0.0 );
	skinMatrix += skinWeight.x * boneMatX;
	skinMatrix += skinWeight.y * boneMatY;
	skinMatrix += skinWeight.z * boneMatZ;
	skinMatrix += skinWeight.w * boneMatW;
	skinMatrix = bindMatrixInverse * skinMatrix * bindMatrix;
	objectNormal = vec4( skinMatrix * vec4( objectNormal, 0.0 ) ).xyz;
	#ifdef USE_TANGENT
		objectTangent = vec4( skinMatrix * vec4( objectTangent, 0.0 ) ).xyz;
	#endif
#endif`,specularmap_fragment:`float specularStrength;
#ifdef USE_SPECULARMAP
	vec4 texelSpecular = texture2D( specularMap, vSpecularMapUv );
	specularStrength = texelSpecular.r;
#else
	specularStrength = 1.0;
#endif`,specularmap_pars_fragment:`#ifdef USE_SPECULARMAP
	uniform sampler2D specularMap;
#endif`,tonemapping_fragment:`#if defined( TONE_MAPPING )
	gl_FragColor.rgb = toneMapping( gl_FragColor.rgb );
#endif`,tonemapping_pars_fragment:`#ifndef saturate
#define saturate( a ) clamp( a, 0.0, 1.0 )
#endif
uniform float toneMappingExposure;
vec3 LinearToneMapping( vec3 color ) {
	return saturate( toneMappingExposure * color );
}
vec3 ReinhardToneMapping( vec3 color ) {
	color *= toneMappingExposure;
	return saturate( color / ( vec3( 1.0 ) + color ) );
}
vec3 CineonToneMapping( vec3 color ) {
	color *= toneMappingExposure;
	color = max( vec3( 0.0 ), color - 0.004 );
	return pow( ( color * ( 6.2 * color + 0.5 ) ) / ( color * ( 6.2 * color + 1.7 ) + 0.06 ), vec3( 2.2 ) );
}
vec3 RRTAndODTFit( vec3 v ) {
	vec3 a = v * ( v + 0.0245786 ) - 0.000090537;
	vec3 b = v * ( 0.983729 * v + 0.4329510 ) + 0.238081;
	return a / b;
}
vec3 ACESFilmicToneMapping( vec3 color ) {
	const mat3 ACESInputMat = mat3(
		vec3( 0.59719, 0.07600, 0.02840 ),		vec3( 0.35458, 0.90834, 0.13383 ),
		vec3( 0.04823, 0.01566, 0.83777 )
	);
	const mat3 ACESOutputMat = mat3(
		vec3(  1.60475, -0.10208, -0.00327 ),		vec3( -0.53108,  1.10813, -0.07276 ),
		vec3( -0.07367, -0.00605,  1.07602 )
	);
	color *= toneMappingExposure / 0.6;
	color = ACESInputMat * color;
	color = RRTAndODTFit( color );
	color = ACESOutputMat * color;
	return saturate( color );
}
const mat3 LINEAR_REC2020_TO_LINEAR_SRGB = mat3(
	vec3( 1.6605, - 0.1246, - 0.0182 ),
	vec3( - 0.5876, 1.1329, - 0.1006 ),
	vec3( - 0.0728, - 0.0083, 1.1187 )
);
const mat3 LINEAR_SRGB_TO_LINEAR_REC2020 = mat3(
	vec3( 0.6274, 0.0691, 0.0164 ),
	vec3( 0.3293, 0.9195, 0.0880 ),
	vec3( 0.0433, 0.0113, 0.8956 )
);
vec3 agxDefaultContrastApprox( vec3 x ) {
	vec3 x2 = x * x;
	vec3 x4 = x2 * x2;
	return + 15.5 * x4 * x2
		- 40.14 * x4 * x
		+ 31.96 * x4
		- 6.868 * x2 * x
		+ 0.4298 * x2
		+ 0.1191 * x
		- 0.00232;
}
vec3 AgXToneMapping( vec3 color ) {
	const mat3 AgXInsetMatrix = mat3(
		vec3( 0.856627153315983, 0.137318972929847, 0.11189821299995 ),
		vec3( 0.0951212405381588, 0.761241990602591, 0.0767994186031903 ),
		vec3( 0.0482516061458583, 0.101439036467562, 0.811302368396859 )
	);
	const mat3 AgXOutsetMatrix = mat3(
		vec3( 1.1271005818144368, - 0.1413297634984383, - 0.14132976349843826 ),
		vec3( - 0.11060664309660323, 1.157823702216272, - 0.11060664309660294 ),
		vec3( - 0.016493938717834573, - 0.016493938717834257, 1.2519364065950405 )
	);
	const float AgxMinEv = - 12.47393;	const float AgxMaxEv = 4.026069;
	color *= toneMappingExposure;
	color = LINEAR_SRGB_TO_LINEAR_REC2020 * color;
	color = AgXInsetMatrix * color;
	color = max( color, 1e-10 );	color = log2( color );
	color = ( color - AgxMinEv ) / ( AgxMaxEv - AgxMinEv );
	color = clamp( color, 0.0, 1.0 );
	color = agxDefaultContrastApprox( color );
	color = AgXOutsetMatrix * color;
	color = pow( max( vec3( 0.0 ), color ), vec3( 2.2 ) );
	color = LINEAR_REC2020_TO_LINEAR_SRGB * color;
	color = clamp( color, 0.0, 1.0 );
	return color;
}
vec3 NeutralToneMapping( vec3 color ) {
	const float StartCompression = 0.8 - 0.04;
	const float Desaturation = 0.15;
	color *= toneMappingExposure;
	float x = min( color.r, min( color.g, color.b ) );
	float offset = x < 0.08 ? x - 6.25 * x * x : 0.04;
	color -= offset;
	float peak = max( color.r, max( color.g, color.b ) );
	if ( peak < StartCompression ) return color;
	float d = 1. - StartCompression;
	float newPeak = 1. - d * d / ( peak + d - StartCompression );
	color *= newPeak / peak;
	float g = 1. - 1. / ( Desaturation * ( peak - newPeak ) + 1. );
	return mix( color, vec3( newPeak ), g );
}
vec3 CustomToneMapping( vec3 color ) { return color; }`,transmission_fragment:`#ifdef USE_TRANSMISSION
	material.transmission = transmission;
	material.transmissionAlpha = 1.0;
	material.thickness = thickness;
	material.attenuationDistance = attenuationDistance;
	material.attenuationColor = attenuationColor;
	#ifdef USE_TRANSMISSIONMAP
		material.transmission *= texture2D( transmissionMap, vTransmissionMapUv ).r;
	#endif
	#ifdef USE_THICKNESSMAP
		material.thickness *= texture2D( thicknessMap, vThicknessMapUv ).g;
	#endif
	vec3 pos = vWorldPosition;
	vec3 v = normalize( cameraPosition - pos );
	vec3 n = transformNormalByInverseViewMatrix( normal, viewMatrix );
	vec4 transmitted = getIBLVolumeRefraction(
		n, v, material.roughness, material.diffuseContribution, material.specularColorBlended, material.specularF90,
		pos, modelMatrix, viewMatrix, projectionMatrix, material.dispersion, material.ior, material.thickness,
		material.attenuationColor, material.attenuationDistance );
	material.transmissionAlpha = mix( material.transmissionAlpha, transmitted.a, material.transmission );
	totalDiffuse = mix( totalDiffuse, transmitted.rgb, material.transmission );
#endif`,transmission_pars_fragment:`#ifdef USE_TRANSMISSION
	uniform float transmission;
	uniform float thickness;
	uniform float attenuationDistance;
	uniform vec3 attenuationColor;
	#ifdef USE_TRANSMISSIONMAP
		uniform sampler2D transmissionMap;
	#endif
	#ifdef USE_THICKNESSMAP
		uniform sampler2D thicknessMap;
	#endif
	uniform vec2 transmissionSamplerSize;
	uniform sampler2D transmissionSamplerMap;
	uniform mat4 modelMatrix;
	uniform mat4 projectionMatrix;
	varying vec3 vWorldPosition;
	float w0( float a ) {
		return ( 1.0 / 6.0 ) * ( a * ( a * ( - a + 3.0 ) - 3.0 ) + 1.0 );
	}
	float w1( float a ) {
		return ( 1.0 / 6.0 ) * ( a *  a * ( 3.0 * a - 6.0 ) + 4.0 );
	}
	float w2( float a ){
		return ( 1.0 / 6.0 ) * ( a * ( a * ( - 3.0 * a + 3.0 ) + 3.0 ) + 1.0 );
	}
	float w3( float a ) {
		return ( 1.0 / 6.0 ) * ( a * a * a );
	}
	float g0( float a ) {
		return w0( a ) + w1( a );
	}
	float g1( float a ) {
		return w2( a ) + w3( a );
	}
	float h0( float a ) {
		return - 1.0 + w1( a ) / ( w0( a ) + w1( a ) );
	}
	float h1( float a ) {
		return 1.0 + w3( a ) / ( w2( a ) + w3( a ) );
	}
	vec4 bicubic( sampler2D tex, vec2 uv, vec4 texelSize, float lod ) {
		uv = uv * texelSize.zw + 0.5;
		vec2 iuv = floor( uv );
		vec2 fuv = fract( uv );
		float g0x = g0( fuv.x );
		float g1x = g1( fuv.x );
		float h0x = h0( fuv.x );
		float h1x = h1( fuv.x );
		float h0y = h0( fuv.y );
		float h1y = h1( fuv.y );
		vec2 p0 = ( vec2( iuv.x + h0x, iuv.y + h0y ) - 0.5 ) * texelSize.xy;
		vec2 p1 = ( vec2( iuv.x + h1x, iuv.y + h0y ) - 0.5 ) * texelSize.xy;
		vec2 p2 = ( vec2( iuv.x + h0x, iuv.y + h1y ) - 0.5 ) * texelSize.xy;
		vec2 p3 = ( vec2( iuv.x + h1x, iuv.y + h1y ) - 0.5 ) * texelSize.xy;
		return g0( fuv.y ) * ( g0x * textureLod( tex, p0, lod ) + g1x * textureLod( tex, p1, lod ) ) +
			g1( fuv.y ) * ( g0x * textureLod( tex, p2, lod ) + g1x * textureLod( tex, p3, lod ) );
	}
	vec4 textureBicubic( sampler2D sampler, vec2 uv, float lod ) {
		vec2 fLodSize = vec2( textureSize( sampler, int( lod ) ) );
		vec2 cLodSize = vec2( textureSize( sampler, int( lod + 1.0 ) ) );
		vec2 fLodSizeInv = 1.0 / fLodSize;
		vec2 cLodSizeInv = 1.0 / cLodSize;
		vec4 fSample = bicubic( sampler, uv, vec4( fLodSizeInv, fLodSize ), floor( lod ) );
		vec4 cSample = bicubic( sampler, uv, vec4( cLodSizeInv, cLodSize ), ceil( lod ) );
		return mix( fSample, cSample, fract( lod ) );
	}
	vec3 getVolumeTransmissionRay( const in vec3 n, const in vec3 v, const in float thickness, const in float ior, const in mat4 modelMatrix ) {
		vec3 refractionVector = refract( - v, normalize( n ), 1.0 / ior );
		vec3 modelScale;
		modelScale.x = length( vec3( modelMatrix[ 0 ].xyz ) );
		modelScale.y = length( vec3( modelMatrix[ 1 ].xyz ) );
		modelScale.z = length( vec3( modelMatrix[ 2 ].xyz ) );
		return normalize( refractionVector ) * thickness * modelScale;
	}
	float applyIorToRoughness( const in float roughness, const in float ior ) {
		return roughness * clamp( ior * 2.0 - 2.0, 0.0, 1.0 );
	}
	vec4 getTransmissionSample( const in vec2 fragCoord, const in float roughness, const in float ior ) {
		float lod = log2( transmissionSamplerSize.x ) * applyIorToRoughness( roughness, ior );
		return textureBicubic( transmissionSamplerMap, fragCoord.xy, lod );
	}
	vec3 volumeAttenuation( const in float transmissionDistance, const in vec3 attenuationColor, const in float attenuationDistance ) {
		if ( isinf( attenuationDistance ) ) {
			return vec3( 1.0 );
		} else {
			vec3 attenuationCoefficient = -log( attenuationColor ) / attenuationDistance;
			vec3 transmittance = exp( - attenuationCoefficient * transmissionDistance );			return transmittance;
		}
	}
	vec4 getIBLVolumeRefraction( const in vec3 n, const in vec3 v, const in float roughness, const in vec3 diffuseColor,
		const in vec3 specularColor, const in float specularF90, const in vec3 position, const in mat4 modelMatrix,
		const in mat4 viewMatrix, const in mat4 projMatrix, const in float dispersion, const in float ior, const in float thickness,
		const in vec3 attenuationColor, const in float attenuationDistance ) {
		vec4 transmittedLight;
		vec3 transmittance;
		#ifdef USE_DISPERSION
			float halfSpread = ( ior - 1.0 ) * 0.025 * dispersion;
			vec3 iors = vec3( ior - halfSpread, ior, ior + halfSpread );
			for ( int i = 0; i < 3; i ++ ) {
				vec3 transmissionRay = getVolumeTransmissionRay( n, v, thickness, iors[ i ], modelMatrix );
				vec3 refractedRayExit = position + transmissionRay;
				vec4 ndcPos = projMatrix * viewMatrix * vec4( refractedRayExit, 1.0 );
				vec2 refractionCoords = ndcPos.xy / ndcPos.w;
				refractionCoords += 1.0;
				refractionCoords /= 2.0;
				vec4 transmissionSample = getTransmissionSample( refractionCoords, roughness, iors[ i ] );
				transmittedLight[ i ] = transmissionSample[ i ];
				transmittedLight.a += transmissionSample.a;
				transmittance[ i ] = diffuseColor[ i ] * volumeAttenuation( length( transmissionRay ), attenuationColor, attenuationDistance )[ i ];
			}
			transmittedLight.a /= 3.0;
		#else
			vec3 transmissionRay = getVolumeTransmissionRay( n, v, thickness, ior, modelMatrix );
			vec3 refractedRayExit = position + transmissionRay;
			vec4 ndcPos = projMatrix * viewMatrix * vec4( refractedRayExit, 1.0 );
			vec2 refractionCoords = ndcPos.xy / ndcPos.w;
			refractionCoords += 1.0;
			refractionCoords /= 2.0;
			transmittedLight = getTransmissionSample( refractionCoords, roughness, ior );
			transmittance = diffuseColor * volumeAttenuation( length( transmissionRay ), attenuationColor, attenuationDistance );
		#endif
		vec3 attenuatedColor = transmittance * transmittedLight.rgb;
		vec3 F = EnvironmentBRDF( n, v, specularColor, specularF90, roughness );
		float transmittanceFactor = ( transmittance.r + transmittance.g + transmittance.b ) / 3.0;
		return vec4( ( 1.0 - F ) * attenuatedColor, 1.0 - ( 1.0 - transmittedLight.a ) * transmittanceFactor );
	}
#endif`,uv_pars_fragment:`#if defined( USE_UV ) || defined( USE_ANISOTROPY )
	varying vec2 vUv;
#endif
#ifdef USE_MAP
	varying vec2 vMapUv;
#endif
#ifdef USE_ALPHAMAP
	varying vec2 vAlphaMapUv;
#endif
#ifdef USE_LIGHTMAP
	varying vec2 vLightMapUv;
#endif
#ifdef USE_AOMAP
	varying vec2 vAoMapUv;
#endif
#ifdef USE_BUMPMAP
	varying vec2 vBumpMapUv;
#endif
#ifdef USE_NORMALMAP
	varying vec2 vNormalMapUv;
#endif
#ifdef USE_EMISSIVEMAP
	varying vec2 vEmissiveMapUv;
#endif
#ifdef USE_METALNESSMAP
	varying vec2 vMetalnessMapUv;
#endif
#ifdef USE_ROUGHNESSMAP
	varying vec2 vRoughnessMapUv;
#endif
#ifdef USE_ANISOTROPYMAP
	varying vec2 vAnisotropyMapUv;
#endif
#ifdef USE_CLEARCOATMAP
	varying vec2 vClearcoatMapUv;
#endif
#ifdef USE_CLEARCOAT_NORMALMAP
	varying vec2 vClearcoatNormalMapUv;
#endif
#ifdef USE_CLEARCOAT_ROUGHNESSMAP
	varying vec2 vClearcoatRoughnessMapUv;
#endif
#ifdef USE_IRIDESCENCEMAP
	varying vec2 vIridescenceMapUv;
#endif
#ifdef USE_IRIDESCENCE_THICKNESSMAP
	varying vec2 vIridescenceThicknessMapUv;
#endif
#ifdef USE_SHEEN_COLORMAP
	varying vec2 vSheenColorMapUv;
#endif
#ifdef USE_SHEEN_ROUGHNESSMAP
	varying vec2 vSheenRoughnessMapUv;
#endif
#ifdef USE_SPECULARMAP
	varying vec2 vSpecularMapUv;
#endif
#ifdef USE_SPECULAR_COLORMAP
	varying vec2 vSpecularColorMapUv;
#endif
#ifdef USE_SPECULAR_INTENSITYMAP
	varying vec2 vSpecularIntensityMapUv;
#endif
#ifdef USE_TRANSMISSIONMAP
	uniform mat3 transmissionMapTransform;
	varying vec2 vTransmissionMapUv;
#endif
#ifdef USE_THICKNESSMAP
	uniform mat3 thicknessMapTransform;
	varying vec2 vThicknessMapUv;
#endif`,uv_pars_vertex:`#if defined( USE_UV ) || defined( USE_ANISOTROPY )
	varying vec2 vUv;
#endif
#ifdef USE_MAP
	uniform mat3 mapTransform;
	varying vec2 vMapUv;
#endif
#ifdef USE_ALPHAMAP
	uniform mat3 alphaMapTransform;
	varying vec2 vAlphaMapUv;
#endif
#ifdef USE_LIGHTMAP
	uniform mat3 lightMapTransform;
	varying vec2 vLightMapUv;
#endif
#ifdef USE_AOMAP
	uniform mat3 aoMapTransform;
	varying vec2 vAoMapUv;
#endif
#ifdef USE_BUMPMAP
	uniform mat3 bumpMapTransform;
	varying vec2 vBumpMapUv;
#endif
#ifdef USE_NORMALMAP
	uniform mat3 normalMapTransform;
	varying vec2 vNormalMapUv;
#endif
#ifdef USE_DISPLACEMENTMAP
	uniform mat3 displacementMapTransform;
	varying vec2 vDisplacementMapUv;
#endif
#ifdef USE_EMISSIVEMAP
	uniform mat3 emissiveMapTransform;
	varying vec2 vEmissiveMapUv;
#endif
#ifdef USE_METALNESSMAP
	uniform mat3 metalnessMapTransform;
	varying vec2 vMetalnessMapUv;
#endif
#ifdef USE_ROUGHNESSMAP
	uniform mat3 roughnessMapTransform;
	varying vec2 vRoughnessMapUv;
#endif
#ifdef USE_ANISOTROPYMAP
	uniform mat3 anisotropyMapTransform;
	varying vec2 vAnisotropyMapUv;
#endif
#ifdef USE_CLEARCOATMAP
	uniform mat3 clearcoatMapTransform;
	varying vec2 vClearcoatMapUv;
#endif
#ifdef USE_CLEARCOAT_NORMALMAP
	uniform mat3 clearcoatNormalMapTransform;
	varying vec2 vClearcoatNormalMapUv;
#endif
#ifdef USE_CLEARCOAT_ROUGHNESSMAP
	uniform mat3 clearcoatRoughnessMapTransform;
	varying vec2 vClearcoatRoughnessMapUv;
#endif
#ifdef USE_SHEEN_COLORMAP
	uniform mat3 sheenColorMapTransform;
	varying vec2 vSheenColorMapUv;
#endif
#ifdef USE_SHEEN_ROUGHNESSMAP
	uniform mat3 sheenRoughnessMapTransform;
	varying vec2 vSheenRoughnessMapUv;
#endif
#ifdef USE_IRIDESCENCEMAP
	uniform mat3 iridescenceMapTransform;
	varying vec2 vIridescenceMapUv;
#endif
#ifdef USE_IRIDESCENCE_THICKNESSMAP
	uniform mat3 iridescenceThicknessMapTransform;
	varying vec2 vIridescenceThicknessMapUv;
#endif
#ifdef USE_SPECULARMAP
	uniform mat3 specularMapTransform;
	varying vec2 vSpecularMapUv;
#endif
#ifdef USE_SPECULAR_COLORMAP
	uniform mat3 specularColorMapTransform;
	varying vec2 vSpecularColorMapUv;
#endif
#ifdef USE_SPECULAR_INTENSITYMAP
	uniform mat3 specularIntensityMapTransform;
	varying vec2 vSpecularIntensityMapUv;
#endif
#ifdef USE_TRANSMISSIONMAP
	uniform mat3 transmissionMapTransform;
	varying vec2 vTransmissionMapUv;
#endif
#ifdef USE_THICKNESSMAP
	uniform mat3 thicknessMapTransform;
	varying vec2 vThicknessMapUv;
#endif`,uv_vertex:`#if defined( USE_UV ) || defined( USE_ANISOTROPY )
	vUv = vec3( uv, 1 ).xy;
#endif
#ifdef USE_MAP
	vMapUv = ( mapTransform * vec3( MAP_UV, 1 ) ).xy;
#endif
#ifdef USE_ALPHAMAP
	vAlphaMapUv = ( alphaMapTransform * vec3( ALPHAMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_LIGHTMAP
	vLightMapUv = ( lightMapTransform * vec3( LIGHTMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_AOMAP
	vAoMapUv = ( aoMapTransform * vec3( AOMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_BUMPMAP
	vBumpMapUv = ( bumpMapTransform * vec3( BUMPMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_NORMALMAP
	vNormalMapUv = ( normalMapTransform * vec3( NORMALMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_DISPLACEMENTMAP
	vDisplacementMapUv = ( displacementMapTransform * vec3( DISPLACEMENTMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_EMISSIVEMAP
	vEmissiveMapUv = ( emissiveMapTransform * vec3( EMISSIVEMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_METALNESSMAP
	vMetalnessMapUv = ( metalnessMapTransform * vec3( METALNESSMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_ROUGHNESSMAP
	vRoughnessMapUv = ( roughnessMapTransform * vec3( ROUGHNESSMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_ANISOTROPYMAP
	vAnisotropyMapUv = ( anisotropyMapTransform * vec3( ANISOTROPYMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_CLEARCOATMAP
	vClearcoatMapUv = ( clearcoatMapTransform * vec3( CLEARCOATMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_CLEARCOAT_NORMALMAP
	vClearcoatNormalMapUv = ( clearcoatNormalMapTransform * vec3( CLEARCOAT_NORMALMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_CLEARCOAT_ROUGHNESSMAP
	vClearcoatRoughnessMapUv = ( clearcoatRoughnessMapTransform * vec3( CLEARCOAT_ROUGHNESSMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_IRIDESCENCEMAP
	vIridescenceMapUv = ( iridescenceMapTransform * vec3( IRIDESCENCEMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_IRIDESCENCE_THICKNESSMAP
	vIridescenceThicknessMapUv = ( iridescenceThicknessMapTransform * vec3( IRIDESCENCE_THICKNESSMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_SHEEN_COLORMAP
	vSheenColorMapUv = ( sheenColorMapTransform * vec3( SHEEN_COLORMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_SHEEN_ROUGHNESSMAP
	vSheenRoughnessMapUv = ( sheenRoughnessMapTransform * vec3( SHEEN_ROUGHNESSMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_SPECULARMAP
	vSpecularMapUv = ( specularMapTransform * vec3( SPECULARMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_SPECULAR_COLORMAP
	vSpecularColorMapUv = ( specularColorMapTransform * vec3( SPECULAR_COLORMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_SPECULAR_INTENSITYMAP
	vSpecularIntensityMapUv = ( specularIntensityMapTransform * vec3( SPECULAR_INTENSITYMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_TRANSMISSIONMAP
	vTransmissionMapUv = ( transmissionMapTransform * vec3( TRANSMISSIONMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_THICKNESSMAP
	vThicknessMapUv = ( thicknessMapTransform * vec3( THICKNESSMAP_UV, 1 ) ).xy;
#endif`,worldpos_vertex:`#if defined( USE_ENVMAP ) || defined( DISTANCE ) || defined ( USE_SHADOWMAP ) || defined ( USE_TRANSMISSION ) || NUM_SPOT_LIGHT_COORDS > 0
	vec4 worldPosition = vec4( transformed, 1.0 );
	#ifdef USE_BATCHING
		worldPosition = batchingMatrix * worldPosition;
	#endif
	#ifdef USE_INSTANCING
		worldPosition = instanceMatrix * worldPosition;
	#endif
	worldPosition = modelMatrix * worldPosition;
#endif`,background_vert:`varying vec2 vUv;
uniform mat3 uvTransform;
void main() {
	vUv = ( uvTransform * vec3( uv, 1 ) ).xy;
	gl_Position = vec4( position.xy, 1.0, 1.0 );
}`,background_frag:`uniform sampler2D t2D;
uniform float backgroundIntensity;
varying vec2 vUv;
void main() {
	vec4 texColor = texture2D( t2D, vUv );
	#ifdef DECODE_VIDEO_TEXTURE
		texColor = vec4( mix( pow( texColor.rgb * 0.9478672986 + vec3( 0.0521327014 ), vec3( 2.4 ) ), texColor.rgb * 0.0773993808, vec3( lessThanEqual( texColor.rgb, vec3( 0.04045 ) ) ) ), texColor.w );
	#endif
	texColor.rgb *= backgroundIntensity;
	gl_FragColor = texColor;
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
}`,backgroundCube_vert:`varying vec3 vWorldDirection;
#include <common>
void main() {
	vWorldDirection = transformDirection( position, modelMatrix );
	#include <begin_vertex>
	#include <project_vertex>
	gl_Position.z = gl_Position.w;
}`,backgroundCube_frag:`#ifdef ENVMAP_TYPE_CUBE
	uniform samplerCube envMap;
#elif defined( ENVMAP_TYPE_CUBE_UV )
	uniform sampler2D envMap;
#endif
uniform float backgroundBlurriness;
uniform float backgroundIntensity;
uniform mat3 backgroundRotation;
varying vec3 vWorldDirection;
#include <cube_uv_reflection_fragment>
void main() {
	#ifdef ENVMAP_TYPE_CUBE
		vec4 texColor = textureCube( envMap, backgroundRotation * vWorldDirection );
	#elif defined( ENVMAP_TYPE_CUBE_UV )
		vec4 texColor = textureCubeUV( envMap, backgroundRotation * vWorldDirection, backgroundBlurriness );
	#else
		vec4 texColor = vec4( 0.0, 0.0, 0.0, 1.0 );
	#endif
	texColor.rgb *= backgroundIntensity;
	gl_FragColor = texColor;
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
}`,cube_vert:`varying vec3 vWorldDirection;
#include <common>
void main() {
	vWorldDirection = transformDirection( position, modelMatrix );
	#include <begin_vertex>
	#include <project_vertex>
	gl_Position.z = gl_Position.w;
}`,cube_frag:`uniform samplerCube tCube;
uniform float tFlip;
uniform float opacity;
varying vec3 vWorldDirection;
void main() {
	vec4 texColor = textureCube( tCube, vec3( tFlip * vWorldDirection.x, vWorldDirection.yz ) );
	gl_FragColor = texColor;
	gl_FragColor.a *= opacity;
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
}`,depth_vert:`#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
varying vec2 vHighPrecisionZW;
void main() {
	#include <uv_vertex>
	#include <batching_vertex>
	#include <skinbase_vertex>
	#include <morphinstance_vertex>
	#ifdef USE_DISPLACEMENTMAP
		#include <beginnormal_vertex>
		#include <morphnormal_vertex>
		#include <skinnormal_vertex>
	#endif
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	vHighPrecisionZW = gl_Position.zw;
}`,depth_frag:`#if DEPTH_PACKING == 3200
	uniform float opacity;
#endif
#include <common>
#include <packing>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
varying vec2 vHighPrecisionZW;
void main() {
	vec4 diffuseColor = vec4( 1.0 );
	#include <clipping_planes_fragment>
	#if DEPTH_PACKING == 3200
		diffuseColor.a = opacity;
	#endif
	#include <map_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <logdepthbuf_fragment>
	#ifdef USE_REVERSED_DEPTH_BUFFER
		float fragCoordZ = vHighPrecisionZW[ 0 ] / vHighPrecisionZW[ 1 ];
	#else
		float fragCoordZ = 0.5 * vHighPrecisionZW[ 0 ] / vHighPrecisionZW[ 1 ] + 0.5;
	#endif
	#if DEPTH_PACKING == 3200
		gl_FragColor = vec4( vec3( 1.0 - fragCoordZ ), opacity );
	#elif DEPTH_PACKING == 3201
		gl_FragColor = packDepthToRGBA( fragCoordZ );
	#elif DEPTH_PACKING == 3202
		gl_FragColor = vec4( packDepthToRGB( fragCoordZ ), 1.0 );
	#elif DEPTH_PACKING == 3203
		gl_FragColor = vec4( packDepthToRG( fragCoordZ ), 0.0, 1.0 );
	#endif
}`,distance_vert:`#define DISTANCE
varying vec3 vWorldPosition;
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <batching_vertex>
	#include <skinbase_vertex>
	#include <morphinstance_vertex>
	#ifdef USE_DISPLACEMENTMAP
		#include <beginnormal_vertex>
		#include <morphnormal_vertex>
		#include <skinnormal_vertex>
	#endif
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <worldpos_vertex>
	#include <clipping_planes_vertex>
	vWorldPosition = worldPosition.xyz;
}`,distance_frag:`#define DISTANCE
uniform vec3 referencePosition;
uniform float nearDistance;
uniform float farDistance;
varying vec3 vWorldPosition;
#include <common>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( 1.0 );
	#include <clipping_planes_fragment>
	#include <map_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	float dist = length( vWorldPosition - referencePosition );
	dist = ( dist - nearDistance ) / ( farDistance - nearDistance );
	dist = saturate( dist );
	gl_FragColor = vec4( dist, 0.0, 0.0, 1.0 );
}`,equirect_vert:`varying vec3 vWorldDirection;
#include <common>
void main() {
	vWorldDirection = transformDirection( position, modelMatrix );
	#include <begin_vertex>
	#include <project_vertex>
}`,equirect_frag:`uniform sampler2D tEquirect;
varying vec3 vWorldDirection;
#include <common>
void main() {
	vec3 direction = normalize( vWorldDirection );
	vec2 sampleUV = equirectUv( direction );
	gl_FragColor = texture2D( tEquirect, sampleUV );
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
}`,linedashed_vert:`uniform float scale;
attribute float lineDistance;
varying float vLineDistance;
#include <common>
#include <uv_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <morphtarget_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	vLineDistance = scale * lineDistance;
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	#include <fog_vertex>
}`,linedashed_frag:`uniform vec3 diffuse;
uniform float opacity;
uniform float dashSize;
uniform float totalSize;
varying float vLineDistance;
#include <common>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <fog_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	if ( mod( vLineDistance, totalSize ) > dashSize ) {
		discard;
	}
	vec3 outgoingLight = vec3( 0.0 );
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	outgoingLight = diffuseColor.rgb;
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
}`,meshbasic_vert:`#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <envmap_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#if defined ( USE_ENVMAP ) || defined ( USE_SKINNING )
		#include <beginnormal_vertex>
		#include <morphnormal_vertex>
		#include <skinbase_vertex>
		#include <skinnormal_vertex>
		#include <defaultnormal_vertex>
	#endif
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	#include <worldpos_vertex>
	#include <envmap_vertex>
	#include <fog_vertex>
}`,meshbasic_frag:`uniform vec3 diffuse;
uniform float opacity;
#ifndef FLAT_SHADED
	varying vec3 vNormal;
#endif
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <aomap_pars_fragment>
#include <lightmap_pars_fragment>
#include <envmap_common_pars_fragment>
#include <envmap_pars_fragment>
#include <fog_pars_fragment>
#include <specularmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <specularmap_fragment>
	ReflectedLight reflectedLight = ReflectedLight( vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ) );
	#ifdef USE_LIGHTMAP
		vec4 lightMapTexel = texture2D( lightMap, vLightMapUv );
		reflectedLight.indirectDiffuse += lightMapTexel.rgb * lightMapIntensity * RECIPROCAL_PI;
	#else
		reflectedLight.indirectDiffuse += vec3( 1.0 );
	#endif
	#include <aomap_fragment>
	reflectedLight.indirectDiffuse *= diffuseColor.rgb;
	vec3 outgoingLight = reflectedLight.indirectDiffuse;
	#include <envmap_fragment>
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,meshlambert_vert:`#define LAMBERT
varying vec3 vViewPosition;
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <envmap_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <shadowmap_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	vViewPosition = - mvPosition.xyz;
	#include <worldpos_vertex>
	#include <envmap_vertex>
	#include <shadowmap_vertex>
	#include <fog_vertex>
}`,meshlambert_frag:`#define LAMBERT
uniform vec3 diffuse;
uniform vec3 emissive;
uniform float opacity;
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <aomap_pars_fragment>
#include <lightmap_pars_fragment>
#include <emissivemap_pars_fragment>
#include <cube_uv_reflection_fragment>
#include <envmap_common_pars_fragment>
#include <envmap_pars_fragment>
#include <envmap_physical_pars_fragment>
#include <fog_pars_fragment>
#include <bsdfs>
#include <lights_pars_begin>
#include <normal_pars_fragment>
#include <lights_lambert_pars_fragment>
#include <shadowmap_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <specularmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	ReflectedLight reflectedLight = ReflectedLight( vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ) );
	vec3 totalEmissiveRadiance = emissive;
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <specularmap_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	#include <emissivemap_fragment>
	#include <lights_lambert_fragment>
	#include <lights_fragment_begin>
	#include <lights_fragment_maps>
	#include <lights_fragment_end>
	#include <aomap_fragment>
	vec3 outgoingLight = reflectedLight.directDiffuse + reflectedLight.indirectDiffuse + totalEmissiveRadiance;
	#include <envmap_fragment>
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,meshmatcap_vert:`#define MATCAP
varying vec3 vViewPosition;
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <color_pars_vertex>
#include <displacementmap_pars_vertex>
#include <fog_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	#include <fog_vertex>
	vViewPosition = - mvPosition.xyz;
}`,meshmatcap_frag:`#define MATCAP
uniform vec3 diffuse;
uniform float opacity;
uniform sampler2D matcap;
varying vec3 vViewPosition;
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <fog_pars_fragment>
#include <normal_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	vec3 viewDir = normalize( vViewPosition );
	vec3 x = normalize( vec3( viewDir.z, 0.0, - viewDir.x ) );
	vec3 y = cross( viewDir, x );
	vec2 uv = vec2( dot( x, normal ), dot( y, normal ) ) * 0.495 + 0.5;
	#ifdef USE_MATCAP
		vec4 matcapColor = texture2D( matcap, uv );
	#else
		vec4 matcapColor = vec4( vec3( mix( 0.2, 0.8, uv.y ) ), 1.0 );
	#endif
	vec3 outgoingLight = diffuseColor.rgb * matcapColor.rgb;
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,meshnormal_vert:`#define NORMAL
#if defined( FLAT_SHADED ) || defined( USE_BUMPMAP ) || defined( USE_NORMALMAP_TANGENTSPACE )
	varying vec3 vViewPosition;
#endif
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphinstance_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
#if defined( FLAT_SHADED ) || defined( USE_BUMPMAP ) || defined( USE_NORMALMAP_TANGENTSPACE )
	vViewPosition = - mvPosition.xyz;
#endif
}`,meshnormal_frag:`#define NORMAL
uniform float opacity;
#if defined( FLAT_SHADED ) || defined( USE_BUMPMAP ) || defined( USE_NORMALMAP_TANGENTSPACE )
	varying vec3 vViewPosition;
#endif
#include <uv_pars_fragment>
#include <normal_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( 0.0, 0.0, 0.0, opacity );
	#include <clipping_planes_fragment>
	#include <logdepthbuf_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	gl_FragColor = vec4( normalize( normal ) * 0.5 + 0.5, diffuseColor.a );
	#ifdef OPAQUE
		gl_FragColor.a = 1.0;
	#endif
}`,meshphong_vert:`#define PHONG
varying vec3 vViewPosition;
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <envmap_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <shadowmap_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphinstance_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	vViewPosition = - mvPosition.xyz;
	#include <worldpos_vertex>
	#include <envmap_vertex>
	#include <shadowmap_vertex>
	#include <fog_vertex>
}`,meshphong_frag:`#define PHONG
uniform vec3 diffuse;
uniform vec3 emissive;
uniform vec3 specular;
uniform float shininess;
uniform float opacity;
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <aomap_pars_fragment>
#include <lightmap_pars_fragment>
#include <emissivemap_pars_fragment>
#include <cube_uv_reflection_fragment>
#include <envmap_common_pars_fragment>
#include <envmap_pars_fragment>
#include <envmap_physical_pars_fragment>
#include <fog_pars_fragment>
#include <bsdfs>
#include <lights_pars_begin>
#include <normal_pars_fragment>
#include <lights_phong_pars_fragment>
#include <shadowmap_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <specularmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	ReflectedLight reflectedLight = ReflectedLight( vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ) );
	vec3 totalEmissiveRadiance = emissive;
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <specularmap_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	#include <emissivemap_fragment>
	#include <lights_phong_fragment>
	#include <lights_fragment_begin>
	#include <lights_fragment_maps>
	#include <lights_fragment_end>
	#include <aomap_fragment>
	vec3 outgoingLight = reflectedLight.directDiffuse + reflectedLight.indirectDiffuse + reflectedLight.directSpecular + reflectedLight.indirectSpecular + totalEmissiveRadiance;
	#include <envmap_fragment>
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,meshphysical_vert:`#define STANDARD
varying vec3 vViewPosition;
#ifdef USE_TRANSMISSION
	varying vec3 vWorldPosition;
#endif
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <shadowmap_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	vViewPosition = - mvPosition.xyz;
	#include <worldpos_vertex>
	#include <shadowmap_vertex>
	#include <fog_vertex>
#ifdef USE_TRANSMISSION
	vWorldPosition = worldPosition.xyz;
#endif
}`,meshphysical_frag:`#define STANDARD
#ifdef PHYSICAL
	#define IOR
	#define USE_SPECULAR
#endif
uniform vec3 diffuse;
uniform vec3 emissive;
uniform float roughness;
uniform float metalness;
uniform float opacity;
#ifdef IOR
	uniform float ior;
#endif
#ifdef USE_SPECULAR
	uniform float specularIntensity;
	uniform vec3 specularColor;
	#ifdef USE_SPECULAR_COLORMAP
		uniform sampler2D specularColorMap;
	#endif
	#ifdef USE_SPECULAR_INTENSITYMAP
		uniform sampler2D specularIntensityMap;
	#endif
#endif
#ifdef USE_CLEARCOAT
	uniform float clearcoat;
	uniform float clearcoatRoughness;
#endif
#ifdef USE_DISPERSION
	uniform float dispersion;
#endif
#ifdef USE_RETROREFLECTION
	uniform float retroreflectivity;
#endif
#ifdef USE_IRIDESCENCE
	uniform float iridescence;
	uniform float iridescenceIOR;
	uniform float iridescenceThicknessMinimum;
	uniform float iridescenceThicknessMaximum;
#endif
#ifdef USE_SHEEN
	uniform vec3 sheenColor;
	uniform float sheenRoughness;
	#ifdef USE_SHEEN_COLORMAP
		uniform sampler2D sheenColorMap;
	#endif
	#ifdef USE_SHEEN_ROUGHNESSMAP
		uniform sampler2D sheenRoughnessMap;
	#endif
#endif
#ifdef USE_ANISOTROPY
	uniform vec2 anisotropyVector;
	#ifdef USE_ANISOTROPYMAP
		uniform sampler2D anisotropyMap;
	#endif
#endif
varying vec3 vViewPosition;
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <aomap_pars_fragment>
#include <lightmap_pars_fragment>
#include <emissivemap_pars_fragment>
#include <iridescence_fragment>
#include <cube_uv_reflection_fragment>
#include <envmap_common_pars_fragment>
#include <envmap_physical_pars_fragment>
#include <fog_pars_fragment>
#include <lights_pars_begin>
#include <normal_pars_fragment>
#include <lights_physical_pars_fragment>
#include <transmission_pars_fragment>
#include <shadowmap_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <clearcoat_pars_fragment>
#include <iridescence_pars_fragment>
#include <roughnessmap_pars_fragment>
#include <metalnessmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	ReflectedLight reflectedLight = ReflectedLight( vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ) );
	vec3 totalEmissiveRadiance = emissive;
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <roughnessmap_fragment>
	#include <metalnessmap_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	#include <clearcoat_normal_fragment_begin>
	#include <clearcoat_normal_fragment_maps>
	#include <emissivemap_fragment>
	#include <lights_physical_fragment>
	#include <lights_fragment_begin>
	#include <lights_fragment_maps>
	#include <lights_fragment_end>
	#include <aomap_fragment>
	vec3 totalDiffuse = reflectedLight.directDiffuse + reflectedLight.indirectDiffuse;
	vec3 totalSpecular = reflectedLight.directSpecular + reflectedLight.indirectSpecular;
	#include <transmission_fragment>
	vec3 outgoingLight = totalDiffuse + totalSpecular + totalEmissiveRadiance;
	#ifdef USE_SHEEN
 
		outgoingLight = outgoingLight + sheenSpecularDirect + sheenSpecularIndirect;
 
 	#endif
	#ifdef USE_CLEARCOAT
		float dotNVcc = saturate( dot( geometryClearcoatNormal, geometryViewDir ) );
		vec3 Fcc = F_Schlick( material.clearcoatF0, material.clearcoatF90, dotNVcc );
		outgoingLight = outgoingLight * ( 1.0 - material.clearcoat * Fcc ) + ( clearcoatSpecularDirect + clearcoatSpecularIndirect ) * material.clearcoat;
	#endif
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,meshtoon_vert:`#define TOON
varying vec3 vViewPosition;
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <shadowmap_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	vViewPosition = - mvPosition.xyz;
	#include <worldpos_vertex>
	#include <shadowmap_vertex>
	#include <fog_vertex>
}`,meshtoon_frag:`#define TOON
uniform vec3 diffuse;
uniform vec3 emissive;
uniform float opacity;
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <aomap_pars_fragment>
#include <lightmap_pars_fragment>
#include <emissivemap_pars_fragment>
#include <gradientmap_pars_fragment>
#include <fog_pars_fragment>
#include <bsdfs>
#include <lights_pars_begin>
#include <normal_pars_fragment>
#include <lights_toon_pars_fragment>
#include <shadowmap_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	ReflectedLight reflectedLight = ReflectedLight( vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ) );
	vec3 totalEmissiveRadiance = emissive;
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	#include <emissivemap_fragment>
	#include <lights_toon_fragment>
	#include <lights_fragment_begin>
	#include <lights_fragment_maps>
	#include <lights_fragment_end>
	#include <aomap_fragment>
	vec3 outgoingLight = reflectedLight.directDiffuse + reflectedLight.indirectDiffuse + totalEmissiveRadiance;
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,points_vert:`uniform float size;
uniform float scale;
#include <common>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <morphtarget_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
#ifdef USE_POINTS_UV
	varying vec2 vUv;
	uniform mat3 uvTransform;
#endif
void main() {
	#ifdef USE_POINTS_UV
		vUv = ( uvTransform * vec3( uv, 1 ) ).xy;
	#endif
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <project_vertex>
	gl_PointSize = size;
	#ifdef USE_SIZEATTENUATION
		bool isPerspective = isPerspectiveMatrix( projectionMatrix );
		if ( isPerspective ) gl_PointSize *= ( scale / - mvPosition.z );
	#endif
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	#include <worldpos_vertex>
	#include <fog_vertex>
}`,points_frag:`uniform vec3 diffuse;
uniform float opacity;
#include <common>
#include <color_pars_fragment>
#include <map_particle_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <fog_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	vec3 outgoingLight = vec3( 0.0 );
	#include <logdepthbuf_fragment>
	#include <map_particle_fragment>
	#include <color_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	outgoingLight = diffuseColor.rgb;
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
}`,shadow_vert:`#include <common>
#include <batching_pars_vertex>
#include <fog_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <shadowmap_pars_vertex>
void main() {
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphinstance_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <worldpos_vertex>
	#include <shadowmap_vertex>
	#include <fog_vertex>
}`,shadow_frag:`uniform vec3 color;
uniform float opacity;
#include <common>
#include <fog_pars_fragment>
#include <bsdfs>
#include <lights_pars_begin>
#include <logdepthbuf_pars_fragment>
#include <shadowmap_pars_fragment>
#include <shadowmask_pars_fragment>
void main() {
	#include <logdepthbuf_fragment>
	gl_FragColor = vec4( color, opacity * ( 1.0 - getShadowMask() ) );
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
}`,sprite_vert:`uniform float rotation;
uniform vec2 center;
#include <common>
#include <uv_pars_vertex>
#include <fog_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	vec4 mvPosition = modelViewMatrix[ 3 ];
	vec2 scale = vec2( length( modelMatrix[ 0 ].xyz ), length( modelMatrix[ 1 ].xyz ) );
	#ifndef USE_SIZEATTENUATION
		bool isPerspective = isPerspectiveMatrix( projectionMatrix );
		if ( isPerspective ) scale *= - mvPosition.z;
	#endif
	vec2 alignedPosition = ( position.xy - ( center - vec2( 0.5 ) ) ) * scale;
	vec2 rotatedPosition;
	rotatedPosition.x = cos( rotation ) * alignedPosition.x - sin( rotation ) * alignedPosition.y;
	rotatedPosition.y = sin( rotation ) * alignedPosition.x + cos( rotation ) * alignedPosition.y;
	mvPosition.xy += rotatedPosition;
	gl_Position = projectionMatrix * mvPosition;
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	#include <fog_vertex>
}`,sprite_frag:`uniform vec3 diffuse;
uniform float opacity;
#include <common>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <fog_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	vec3 outgoingLight = vec3( 0.0 );
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	outgoingLight = diffuseColor.rgb;
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
}`},l={common:{diffuse:{value:new r.Q1f(0xffffff)},opacity:{value:1},map:{value:null},mapTransform:{value:new r.dwI},alphaMap:{value:null},alphaMapTransform:{value:new r.dwI},alphaTest:{value:0}},specularmap:{specularMap:{value:null},specularMapTransform:{value:new r.dwI}},envmap:{envMap:{value:null},envMapRotation:{value:new r.dwI},reflectivity:{value:1},ior:{value:1.5},refractionRatio:{value:.98},dfgLUT:{value:null}},aomap:{aoMap:{value:null},aoMapIntensity:{value:1},aoMapTransform:{value:new r.dwI}},lightmap:{lightMap:{value:null},lightMapIntensity:{value:1},lightMapTransform:{value:new r.dwI}},bumpmap:{bumpMap:{value:null},bumpMapTransform:{value:new r.dwI},bumpScale:{value:1}},normalmap:{normalMap:{value:null},normalMapTransform:{value:new r.dwI},normalScale:{value:new r.I9Y(1,1)}},displacementmap:{displacementMap:{value:null},displacementMapTransform:{value:new r.dwI},displacementScale:{value:1},displacementBias:{value:0}},emissivemap:{emissiveMap:{value:null},emissiveMapTransform:{value:new r.dwI}},metalnessmap:{metalnessMap:{value:null},metalnessMapTransform:{value:new r.dwI}},roughnessmap:{roughnessMap:{value:null},roughnessMapTransform:{value:new r.dwI}},gradientmap:{gradientMap:{value:null}},fog:{fogDensity:{value:25e-5},fogNear:{value:1},fogFar:{value:2e3},fogColor:{value:new r.Q1f(0xffffff)}},lights:{ambientLightColor:{value:[]},lightProbe:{value:[]},sunLights:{value:[],properties:{direction:{},color:{}}},sunLightShadows:{value:[],properties:{shadowIntensity:1,shadowBias:{},shadowNormalBias:{},shadowRadius:{},shadowMapSize:{}}},sunShadowMatrix:{value:[]},sunShadowCascade:{value:[]},directionalLights:{value:[],properties:{direction:{},color:{}}},directionalLightShadows:{value:[],properties:{shadowIntensity:1,shadowBias:{},shadowNormalBias:{},shadowRadius:{},shadowMapSize:{}}},directionalShadowMatrix:{value:[]},spotLights:{value:[],properties:{color:{},position:{},direction:{},distance:{},coneCos:{},penumbraCos:{},decay:{}}},spotLightShadows:{value:[],properties:{shadowIntensity:1,shadowBias:{},shadowNormalBias:{},shadowRadius:{},shadowMapSize:{}}},spotLightMap:{value:[]},spotLightMatrix:{value:[]},pointLights:{value:[],properties:{color:{},position:{},decay:{},distance:{}}},pointLightShadows:{value:[],properties:{shadowIntensity:1,shadowBias:{},shadowNormalBias:{},shadowRadius:{},shadowMapSize:{},shadowCameraNear:{},shadowCameraFar:{}}},pointShadowMatrix:{value:[]},hemisphereLights:{value:[],properties:{direction:{},skyColor:{},groundColor:{}}},rectAreaLights:{value:[],properties:{color:{},position:{},width:{},height:{}}},ltc_1:{value:null},ltc_2:{value:null},probesSH:{value:null},probesMin:{value:new r.Pq0},probesMax:{value:new r.Pq0},probesResolution:{value:new r.Pq0}},points:{diffuse:{value:new r.Q1f(0xffffff)},opacity:{value:1},size:{value:1},scale:{value:1},map:{value:null},alphaMap:{value:null},alphaMapTransform:{value:new r.dwI},alphaTest:{value:0},uvTransform:{value:new r.dwI}},sprite:{diffuse:{value:new r.Q1f(0xffffff)},opacity:{value:1},center:{value:new r.I9Y(.5,.5)},rotation:{value:0},map:{value:null},mapTransform:{value:new r.dwI},alphaMap:{value:null},alphaMapTransform:{value:new r.dwI},alphaTest:{value:0}}},s={basic:{uniforms:(0,r.Iit)([l.common,l.specularmap,l.envmap,l.aomap,l.lightmap,l.fog]),vertexShader:o.meshbasic_vert,fragmentShader:o.meshbasic_frag},lambert:{uniforms:(0,r.Iit)([l.common,l.specularmap,l.envmap,l.aomap,l.lightmap,l.emissivemap,l.bumpmap,l.normalmap,l.displacementmap,l.fog,l.lights,{emissive:{value:new r.Q1f(0)},envMapIntensity:{value:1}}]),vertexShader:o.meshlambert_vert,fragmentShader:o.meshlambert_frag},phong:{uniforms:(0,r.Iit)([l.common,l.specularmap,l.envmap,l.aomap,l.lightmap,l.emissivemap,l.bumpmap,l.normalmap,l.displacementmap,l.fog,l.lights,{emissive:{value:new r.Q1f(0)},specular:{value:new r.Q1f(1118481)},shininess:{value:30},envMapIntensity:{value:1}}]),vertexShader:o.meshphong_vert,fragmentShader:o.meshphong_frag},standard:{uniforms:(0,r.Iit)([l.common,l.envmap,l.aomap,l.lightmap,l.emissivemap,l.bumpmap,l.normalmap,l.displacementmap,l.roughnessmap,l.metalnessmap,l.fog,l.lights,{emissive:{value:new r.Q1f(0)},roughness:{value:1},metalness:{value:0},envMapIntensity:{value:1}}]),vertexShader:o.meshphysical_vert,fragmentShader:o.meshphysical_frag},toon:{uniforms:(0,r.Iit)([l.common,l.aomap,l.lightmap,l.emissivemap,l.bumpmap,l.normalmap,l.displacementmap,l.gradientmap,l.fog,l.lights,{emissive:{value:new r.Q1f(0)}}]),vertexShader:o.meshtoon_vert,fragmentShader:o.meshtoon_frag},matcap:{uniforms:(0,r.Iit)([l.common,l.bumpmap,l.normalmap,l.displacementmap,l.fog,{matcap:{value:null}}]),vertexShader:o.meshmatcap_vert,fragmentShader:o.meshmatcap_frag},points:{uniforms:(0,r.Iit)([l.points,l.fog]),vertexShader:o.points_vert,fragmentShader:o.points_frag},dashed:{uniforms:(0,r.Iit)([l.common,l.fog,{scale:{value:1},dashSize:{value:1},totalSize:{value:2}}]),vertexShader:o.linedashed_vert,fragmentShader:o.linedashed_frag},depth:{uniforms:(0,r.Iit)([l.common,l.displacementmap]),vertexShader:o.depth_vert,fragmentShader:o.depth_frag},normal:{uniforms:(0,r.Iit)([l.common,l.bumpmap,l.normalmap,l.displacementmap,{opacity:{value:1}}]),vertexShader:o.meshnormal_vert,fragmentShader:o.meshnormal_frag},sprite:{uniforms:(0,r.Iit)([l.sprite,l.fog]),vertexShader:o.sprite_vert,fragmentShader:o.sprite_frag},background:{uniforms:{uvTransform:{value:new r.dwI},t2D:{value:null},backgroundIntensity:{value:1}},vertexShader:o.background_vert,fragmentShader:o.background_frag},backgroundCube:{uniforms:{envMap:{value:null},backgroundBlurriness:{value:0},backgroundIntensity:{value:1},backgroundRotation:{value:new r.dwI}},vertexShader:o.backgroundCube_vert,fragmentShader:o.backgroundCube_frag},cube:{uniforms:{tCube:{value:null},tFlip:{value:-1},opacity:{value:1}},vertexShader:o.cube_vert,fragmentShader:o.cube_frag},equirect:{uniforms:{tEquirect:{value:null}},vertexShader:o.equirect_vert,fragmentShader:o.equirect_frag},distance:{uniforms:(0,r.Iit)([l.common,l.displacementmap,{referencePosition:{value:new r.Pq0},nearDistance:{value:1},farDistance:{value:1e3}}]),vertexShader:o.distance_vert,fragmentShader:o.distance_frag},shadow:{uniforms:(0,r.Iit)([l.lights,l.fog,{color:{value:new r.Q1f(0)},opacity:{value:1}}]),vertexShader:o.shadow_vert,fragmentShader:o.shadow_frag}};s.physical={uniforms:(0,r.Iit)([s.standard.uniforms,{clearcoat:{value:0},clearcoatMap:{value:null},clearcoatMapTransform:{value:new r.dwI},clearcoatNormalMap:{value:null},clearcoatNormalMapTransform:{value:new r.dwI},clearcoatNormalScale:{value:new r.I9Y(1,1)},clearcoatRoughness:{value:0},clearcoatRoughnessMap:{value:null},clearcoatRoughnessMapTransform:{value:new r.dwI},dispersion:{value:0},retroreflectivity:{value:0},iridescence:{value:0},iridescenceMap:{value:null},iridescenceMapTransform:{value:new r.dwI},iridescenceIOR:{value:1.3},iridescenceThicknessMinimum:{value:100},iridescenceThicknessMaximum:{value:400},iridescenceThicknessMap:{value:null},iridescenceThicknessMapTransform:{value:new r.dwI},sheen:{value:0},sheenColor:{value:new r.Q1f(0)},sheenColorMap:{value:null},sheenColorMapTransform:{value:new r.dwI},sheenRoughness:{value:1},sheenRoughnessMap:{value:null},sheenRoughnessMapTransform:{value:new r.dwI},transmission:{value:0},transmissionMap:{value:null},transmissionMapTransform:{value:new r.dwI},transmissionSamplerSize:{value:new r.I9Y},transmissionSamplerMap:{value:null},thickness:{value:0},thicknessMap:{value:null},thicknessMapTransform:{value:new r.dwI},attenuationDistance:{value:0},attenuationColor:{value:new r.Q1f(0)},specularColor:{value:new r.Q1f(1,1,1)},specularColorMap:{value:null},specularColorMapTransform:{value:new r.dwI},specularIntensity:{value:1},specularIntensityMap:{value:null},specularIntensityMapTransform:{value:new r.dwI},anisotropyVector:{value:new r.I9Y},anisotropyMap:{value:null},anisotropyMapTransform:{value:new r.dwI}}]),vertexShader:o.meshphysical_vert,fragmentShader:o.meshphysical_frag};let c={r:0,b:0,g:0},d=new r.kn4,u=new r.dwI;function f(e,t,i,a,n,o){let l,f,p=new r.Q1f(0),m=+(!0!==n),h=null,_=0,g=null;function v(e){let i=!0===e.isScene?e.background:null;if(i&&i.isTexture){let r=e.backgroundBlurriness>0;i=t.get(i,r)}return i}function S(t,a){t.getRGB(c,(0,r._Ut)(e)),i.buffers.color.setClear(c.r,c.g,c.b,a,o)}return{getClearColor:function(){return p},setClearColor:function(e){let t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:1;p.set(e),S(p,m=t)},getClearAlpha:function(){return m},setClearAlpha:function(e){S(p,m=e)},render:function(t){let r=!1,a=v(t);null===a?S(p,m):a&&a.isColor&&(S(a,1),r=!0);let n=e.xr.getEnvironmentBlendMode();"additive"===n?i.buffers.color.setClear(0,0,0,1,o):"alpha-blend"===n&&i.buffers.color.setClear(0,0,0,0,o),(e.autoClear||r)&&(i.buffers.depth.setTest(!0),i.buffers.depth.setMask(!0),i.buffers.color.setMask(!0),e.clear(e.autoClearColor,e.autoClearDepth,e.autoClearStencil))},addToRenderList:function(t,i){let n=v(i);n&&(n.isCubeTexture||306===n.mapping)?(void 0===f&&((f=new r.eaF(new r.iNn(1,1,1),new r.BKk({name:"BackgroundCubeMaterial",uniforms:(0,r.lxW)(s.backgroundCube.uniforms),vertexShader:s.backgroundCube.vertexShader,fragmentShader:s.backgroundCube.fragmentShader,side:1,depthTest:!1,depthWrite:!1,fog:!1,allowOverride:!1}))).geometry.deleteAttribute("normal"),f.geometry.deleteAttribute("uv"),f.onBeforeRender=function(e,t,i){this.matrixWorld.copyPosition(i.matrixWorld)},Object.defineProperty(f.material,"envMap",{get:function(){return this.uniforms.envMap.value}}),a.update(f)),f.material.uniforms.envMap.value=n,f.material.uniforms.backgroundBlurriness.value=i.backgroundBlurriness,f.material.uniforms.backgroundIntensity.value=i.backgroundIntensity,f.material.uniforms.backgroundRotation.value.setFromMatrix4(d.makeRotationFromEuler(i.backgroundRotation)).transpose(),n.isCubeTexture&&!1===n.isRenderTargetTexture&&f.material.uniforms.backgroundRotation.value.premultiply(u),f.material.toneMapped="srgb"!==r.ppV.getTransfer(n.colorSpace),(h!==n||_!==n.version||g!==e.toneMapping)&&(f.material.needsUpdate=!0,h=n,_=n.version,g=e.toneMapping),f.layers.enableAll(),t.unshift(f,f.geometry,f.material,0,0,null)):n&&n.isTexture&&(void 0===l&&((l=new r.eaF(new r.bdM(2,2),new r.BKk({name:"BackgroundMaterial",uniforms:(0,r.lxW)(s.background.uniforms),vertexShader:s.background.vertexShader,fragmentShader:s.background.fragmentShader,side:0,depthTest:!1,depthWrite:!1,fog:!1,allowOverride:!1}))).geometry.deleteAttribute("normal"),Object.defineProperty(l.material,"map",{get:function(){return this.uniforms.t2D.value}}),a.update(l)),l.material.uniforms.t2D.value=n,l.material.uniforms.backgroundIntensity.value=i.backgroundIntensity,l.material.toneMapped="srgb"!==r.ppV.getTransfer(n.colorSpace),!0===n.matrixAutoUpdate&&n.updateMatrix(),l.material.uniforms.uvTransform.value.copy(n.matrix),(h!==n||_!==n.version||g!==e.toneMapping)&&(l.material.needsUpdate=!0,h=n,_=n.version,g=e.toneMapping),l.layers.enableAll(),t.unshift(l,l.geometry,l.material,0,0,null))},dispose:function(){void 0!==f&&(f.geometry.dispose(),f.material.dispose(),f=void 0),void 0!==l&&(l.geometry.dispose(),l.material.dispose(),l=void 0)}}}function p(e,t){let i=e.getParameter(e.MAX_VERTEX_ATTRIBS),r={},a=c(null),n=a,o=!1;function l(t){return e.bindVertexArray(t)}function s(t){return e.deleteVertexArray(t)}function c(e){let t=[],r=[],a=[];for(let e=0;e<i;e++)t[e]=0,r[e]=0,a[e]=0;return{geometry:null,program:null,wireframe:!1,newAttributes:t,enabledAttributes:r,attributeDivisors:a,object:e,attributes:{},index:null}}function d(){let e=n.newAttributes;for(let t=0,i=e.length;t<i;t++)e[t]=0}function u(e){f(e,0)}function f(t,i){let r=n.newAttributes,a=n.enabledAttributes,o=n.attributeDivisors;r[t]=1,0===a[t]&&(e.enableVertexAttribArray(t),a[t]=1),o[t]!==i&&(e.vertexAttribDivisor(t,i),o[t]=i)}function p(){let t=n.newAttributes,i=n.enabledAttributes;for(let r=0,a=i.length;r<a;r++)i[r]!==t[r]&&(e.disableVertexAttribArray(r),i[r]=0)}function m(t,i,r,a,n,o,l){!0===l?e.vertexAttribIPointer(t,i,r,n,o):e.vertexAttribPointer(t,i,r,a,n,o)}function h(){_(),o=!0,n!==a&&l((n=a).object)}function _(){a.geometry=null,a.program=null,a.wireframe=!1}return{setup:function(i,a,s,h,_){var g,v,S;let E,M,T,x,R,A,b=!1,C=(g=i,v=h,S=s,E=!0===a.wireframe,void 0===(M=r[v.id])&&(M={},r[v.id]=M),void 0===(x=M[T=!0===g.isInstancedMesh?g.id:0])&&(x={},M[T]=x),void 0===(R=x[S.id])&&(R={},x[S.id]=R),void 0===(A=R[E])&&(A=c(e.createVertexArray()),R[E]=A),A);n!==C&&l((n=C).object),(b=function(e,t,i,r){let a=n.attributes,o=t.attributes,l=0,s=i.getAttributes();for(let t in s)if(s[t].location>=0){let i=a[t],r=o[t];if(void 0===r&&("instanceMatrix"===t&&e.instanceMatrix&&(r=e.instanceMatrix),"instanceColor"===t&&e.instanceColor&&(r=e.instanceColor)),void 0===i||i.attribute!==r||r&&i.data!==r.data)return!0;l++}return n.attributesNum!==l||n.index!==r}(i,h,s,_))&&function(e,t,i,r){let a={},o=t.attributes,l=0,s=i.getAttributes();for(let t in s)if(s[t].location>=0){let i=o[t];void 0===i&&("instanceMatrix"===t&&e.instanceMatrix&&(i=e.instanceMatrix),"instanceColor"===t&&e.instanceColor&&(i=e.instanceColor));let r={};r.attribute=i,i&&i.data&&(r.data=i.data),a[t]=r,l++}n.attributes=a,n.attributesNum=l,n.index=r}(i,h,s,_),null!==_&&t.update(_,e.ELEMENT_ARRAY_BUFFER),(b||o)&&(o=!1,function(i,r,a,n){d();let o=n.attributes,l=a.getAttributes(),s=r.defaultAttributeValues;for(let r in l){let a=l[r];if(a.location>=0){let l=o[r];if(void 0===l&&("instanceMatrix"===r&&i.instanceMatrix&&(l=i.instanceMatrix),"instanceColor"===r&&i.instanceColor&&(l=i.instanceColor)),void 0!==l){let r=l.normalized,o=l.itemSize,s=t.get(l);if(void 0===s)continue;let c=s.buffer,d=s.type,p=s.bytesPerElement,h=d===e.INT||d===e.UNSIGNED_INT||1013===l.gpuType;if(l.isInterleavedBufferAttribute){let t=l.data,s=t.stride,_=l.offset;if(t.isInstancedInterleavedBuffer){for(let e=0;e<a.locationSize;e++)f(a.location+e,t.meshPerAttribute);!0!==i.isInstancedMesh&&void 0===n._maxInstanceCount&&(n._maxInstanceCount=t.meshPerAttribute*t.count)}else for(let e=0;e<a.locationSize;e++)u(a.location+e);e.bindBuffer(e.ARRAY_BUFFER,c);for(let e=0;e<a.locationSize;e++)m(a.location+e,o/a.locationSize,d,r,s*p,(_+o/a.locationSize*e)*p,h)}else{if(l.isInstancedBufferAttribute){for(let e=0;e<a.locationSize;e++)f(a.location+e,l.meshPerAttribute);!0!==i.isInstancedMesh&&void 0===n._maxInstanceCount&&(n._maxInstanceCount=l.meshPerAttribute*l.count)}else for(let e=0;e<a.locationSize;e++)u(a.location+e);e.bindBuffer(e.ARRAY_BUFFER,c);for(let e=0;e<a.locationSize;e++)m(a.location+e,o/a.locationSize,d,r,o*p,o/a.locationSize*e*p,h)}}else if(void 0!==s){let t=s[r];if(void 0!==t)switch(t.length){case 2:e.vertexAttrib2fv(a.location,t);break;case 3:e.vertexAttrib3fv(a.location,t);break;case 4:e.vertexAttrib4fv(a.location,t);break;default:e.vertexAttrib1fv(a.location,t)}}}}p()}(i,a,s,h),null!==_&&e.bindBuffer(e.ELEMENT_ARRAY_BUFFER,t.get(_).buffer))},reset:h,resetDefaultState:_,dispose:function(){for(let e in h(),r){let t=r[e];for(let e in t){let i=t[e];for(let e in i){let t=i[e];for(let e in t)s(t[e].object),delete t[e];delete i[e]}}delete r[e]}},releaseStatesOfGeometry:function(e){if(void 0===r[e.id])return;let t=r[e.id];for(let e in t){let i=t[e];for(let e in i){let t=i[e];for(let e in t)s(t[e].object),delete t[e];delete i[e]}}delete r[e.id]},releaseStatesOfObject:function(e){for(let t in r){let i=r[t],a=!0===e.isInstancedMesh?e.id:0,n=i[a];if(void 0!==n){for(let e in n){let t=n[e];for(let e in t)s(t[e].object),delete t[e];delete n[e]}delete i[a],0===Object.keys(i).length&&delete r[t]}}},releaseStatesOfProgram:function(e){for(let t in r){let i=r[t];for(let t in i){let r=i[t];if(void 0===r[e.id])continue;let a=r[e.id];for(let e in a)s(a[e].object),delete a[e];delete r[e.id]}}},initAttributes:d,enableAttribute:u,disableUnusedAttributes:p}}function m(e,t,i){let r;this.setMode=function(e){r=e},this.render=function(t,a){e.drawArrays(r,t,a),i.update(a,r,1)},this.renderInstances=function(t,a,n){0!==n&&(e.drawArraysInstanced(r,t,a,n),i.update(a,r,n))},this.renderMultiDraw=function(e,a,n){if(0===n)return;t.get("WEBGL_multi_draw").multiDrawArraysWEBGL(r,e,0,a,0,n);let o=0;for(let e=0;e<n;e++)o+=a[e];i.update(o,r,1)}}function h(e,t,i,a){let n;function o(t){if("highp"===t){if(e.getShaderPrecisionFormat(e.VERTEX_SHADER,e.HIGH_FLOAT).precision>0&&e.getShaderPrecisionFormat(e.FRAGMENT_SHADER,e.HIGH_FLOAT).precision>0)return"highp";t="mediump"}return"mediump"===t&&e.getShaderPrecisionFormat(e.VERTEX_SHADER,e.MEDIUM_FLOAT).precision>0&&e.getShaderPrecisionFormat(e.FRAGMENT_SHADER,e.MEDIUM_FLOAT).precision>0?"mediump":"lowp"}let l=void 0!==i.precision?i.precision:"highp",s=o(l);s!==l&&((0,r.R8M)("WebGLRenderer:",l,"not supported, using",s,"instead."),l=s);let c=!0===i.logarithmicDepthBuffer,d=!0===i.reversedDepthBuffer&&t.has("EXT_clip_control");return!0===i.reversedDepthBuffer&&!1===d&&(0,r.R8M)("WebGLRenderer: Unable to use reversed depth buffer due to missing EXT_clip_control extension. Fallback to default depth buffer."),{isWebGL2:!0,getMaxAnisotropy:function(){if(void 0!==n)return n;if(!0===t.has("EXT_texture_filter_anisotropic")){let i=t.get("EXT_texture_filter_anisotropic");n=e.getParameter(i.MAX_TEXTURE_MAX_ANISOTROPY_EXT)}else n=0;return n},getMaxPrecision:o,textureFormatReadable:function(t){return 1023===t||a.convert(t)===e.getParameter(e.IMPLEMENTATION_COLOR_READ_FORMAT)},textureTypeReadable:function(i){let r=1016===i&&(t.has("EXT_color_buffer_half_float")||t.has("EXT_color_buffer_float"));return 1009===i||1015===i||!!r||a.convert(i)===e.getParameter(e.IMPLEMENTATION_COLOR_READ_TYPE)},precision:l,logarithmicDepthBuffer:c,reversedDepthBuffer:d,maxTextures:e.getParameter(e.MAX_TEXTURE_IMAGE_UNITS),maxVertexTextures:e.getParameter(e.MAX_VERTEX_TEXTURE_IMAGE_UNITS),maxTextureSize:e.getParameter(e.MAX_TEXTURE_SIZE),maxCubemapSize:e.getParameter(e.MAX_CUBE_MAP_TEXTURE_SIZE),maxAttributes:e.getParameter(e.MAX_VERTEX_ATTRIBS),maxVertexUniforms:e.getParameter(e.MAX_VERTEX_UNIFORM_VECTORS),maxVaryings:e.getParameter(e.MAX_VARYING_VECTORS),maxFragmentUniforms:e.getParameter(e.MAX_FRAGMENT_UNIFORM_VECTORS),maxSamples:e.getParameter(e.MAX_SAMPLES),samples:e.getParameter(e.SAMPLES)}}function _(e){let t=this,i=null,a=0,n=!1,o=!1,l=new r.Zcv,s=new r.dwI,c={value:null,needsUpdate:!1};function d(e,i,r,a){let n=null!==e?e.length:0,o=null;if(0!==n){if(o=c.value,!0!==a||null===o){let t=r+4*n,a=i.matrixWorldInverse;s.getNormalMatrix(a),(null===o||o.length<t)&&(o=new Float32Array(t));for(let t=0,i=r;t!==n;++t,i+=4)l.copy(e[t]).applyMatrix4(a,s),l.normal.toArray(o,i),o[i+3]=l.constant}c.value=o,c.needsUpdate=!0}return t.numPlanes=n,t.numIntersection=0,o}this.uniform=c,this.numPlanes=0,this.numIntersection=0,this.init=function(e,t){let i=0!==e.length||t||0!==a||n;return n=t,a=e.length,i},this.beginShadows=function(){o=!0,d(null)},this.endShadows=function(){o=!1},this.setGlobalState=function(e,t){i=d(e,t,0)},this.setState=function(r,l,s){let u=r.clippingPlanes,f=r.clipIntersection,p=r.clipShadows,m=e.get(r);if(n&&null!==u&&0!==u.length&&(!o||p)){let e=o?0:a,t=4*e,r=m.clippingState||null;c.value=r,r=d(u,l,t,s);for(let e=0;e!==t;++e)r[e]=i[e];m.clippingState=r,this.numIntersection=f?this.numPlanes:0,this.numPlanes+=e}else o?d(null):(c.value!==i&&(c.value=i,c.needsUpdate=a>0),t.numPlanes=a,t.numIntersection=0)}}u.set(-1,0,0,0,1,0,0,0,1);let g=new r.qUd,v=new r.Q1f,S=null,E=0,M=0,T=!1,x=new r.Pq0,R=new r.Pq0;let PMREMGenerator=class PMREMGenerator{fromScene(e){let t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:0,i=arguments.length>2&&void 0!==arguments[2]?arguments[2]:.1,r=arguments.length>3&&void 0!==arguments[3]?arguments[3]:100,a=arguments.length>4&&void 0!==arguments[4]?arguments[4]:{},{size:n=256,position:o=x}=a;S=this._renderer.getRenderTarget(),E=this._renderer.getActiveCubeFace(),M=this._renderer.getActiveMipmapLevel(),T=this._renderer.xr.enabled,this._renderer.xr.enabled=!1,this._setSize(n);let l=this._allocateTargets();return l.depthBuffer=!0,this._sceneToCubeUV(e,i,r,l,o),t>0&&this._blur(l,0,0,t),this._applyPMREM(l),this._cleanup(l),l}fromEquirectangular(e){let t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:null;return this._fromTexture(e,t)}fromCubemap(e){let t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:null;return this._fromTexture(e,t)}compileCubemapShader(){null===this._cubemapMaterial&&(this._cubemapMaterial=P(),this._compileMaterial(this._cubemapMaterial))}compileEquirectangularShader(){null===this._equirectMaterial&&(this._equirectMaterial=C(),this._compileMaterial(this._equirectMaterial))}dispose(){this._dispose(),null!==this._cubemapMaterial&&this._cubemapMaterial.dispose(),null!==this._equirectMaterial&&this._equirectMaterial.dispose(),null!==this._backgroundBox&&(this._backgroundBox.geometry.dispose(),this._backgroundBox.material.dispose())}_setSize(e){this._lodMax=Math.floor(Math.log2(e)),this._cubeSize=Math.pow(2,this._lodMax)}_dispose(){null!==this._blurMaterial&&this._blurMaterial.dispose(),null!==this._ggxMaterial&&this._ggxMaterial.dispose(),null!==this._pingPongRenderTarget&&this._pingPongRenderTarget.dispose();for(let e=0;e<this._lodMeshes.length;e++)this._lodMeshes[e].geometry.dispose()}_cleanup(e){this._renderer.setRenderTarget(S,E,M),this._renderer.xr.enabled=T,e.scissorTest=!1,b(e,0,0,e.width,e.height)}_fromTexture(e,t){301===e.mapping||302===e.mapping?this._setSize(0===e.image.length?16:e.image[0].width||e.image[0].image.width):this._setSize(e.image.width/4),S=this._renderer.getRenderTarget(),E=this._renderer.getActiveCubeFace(),M=this._renderer.getActiveMipmapLevel(),T=this._renderer.xr.enabled,this._renderer.xr.enabled=!1;let i=t||this._allocateTargets();return this._textureToCubeUV(e,i),this._applyPMREM(i),this._cleanup(i),i}_allocateTargets(){let e=3*Math.max(this._cubeSize,112),t=4*this._cubeSize,i={magFilter:1006,minFilter:1006,generateMipmaps:!1,type:1016,format:1023,colorSpace:r.Zr2,depthBuffer:!1},a=A(e,t,i);if(null===this._pingPongRenderTarget||this._pingPongRenderTarget.width!==e||this._pingPongRenderTarget.height!==t){var n,o,l,s,c,d;null!==this._pingPongRenderTarget&&this._dispose(),this._pingPongRenderTarget=A(e,t,i);let{_lodMax:a}=this;({lodMeshes:this._lodMeshes,sizeLods:this._sizeLods}=function(e){let t=[],i=[],a=e,n=e-4+1+6;for(let e=0;e<n;e++){let e=Math.pow(2,a);t.push(e);let n=1/(e-2),o=-n,l=1+n,s=[o,o,l,o,l,l,o,o,l,l,o,l],c=new Float32Array(108),d=new Float32Array(108);for(let e=0;e<6;e++){let t=e%3*2/3-1,i=e>2?0:-1,r=[t,i,0,t+2/3,i,0,t+2/3,i+1,0,t,i,0,t+2/3,i+1,0,t,i+1,0];c.set(r,18*e);for(let t=0;t<6;t++){let i=2*s[2*t]-1,r=2*s[2*t+1]-1;0===e?R.set(1,r,i):1===e?R.set(-i,1,-r):2===e?R.set(-i,r,1):3===e?R.set(-1,r,-i):4===e?R.set(-i,-1,r):R.set(i,r,-1),R.toArray(d,(6*e+t)*3)}}let u=new r.LoY;u.setAttribute("position",new r.THS(c,3)),u.setAttribute("outputDirection",new r.THS(d,3)),i.push(new r.eaF(u,null)),a>4&&a--}return{lodMeshes:i,sizeLods:t}}(a)),this._blurMaterial=(n=a,o=e,l=t,new r.BKk({name:"SphericalGaussianBlur",defines:{SAMPLES:20,CUBEUV_TEXEL_WIDTH:1/o,CUBEUV_TEXEL_HEIGHT:1/l,CUBEUV_MAX_MIP:"".concat(n,".0")},uniforms:{envMap:{value:null},sigma:{value:0},mipInt:{value:0}},vertexShader:L(),fragmentShader:`

			precision highp float;
			precision highp int;

			varying vec3 vOutputDirection;

			uniform sampler2D envMap;
			uniform float sigma;
			uniform float mipInt;

			#define ENVMAP_TYPE_CUBE_UV
			#include <cube_uv_reflection_fragment>

			#define PI 3.14159265359
			#define GOLDEN_ANGLE 2.39996322973

			void main() {

				if ( sigma == 0.0 ) {

					gl_FragColor = vec4( bilinearCubeUV( envMap, vOutputDirection, mipInt ), 1.0 );
					return;

				}

				vec3 outputDirection = normalize( vOutputDirection );

				vec3 up = abs( outputDirection.z ) < 0.999 ? vec3( 0.0, 0.0, 1.0 ) : vec3( 1.0, 0.0, 0.0 );
				vec3 tangent = normalize( cross( up, outputDirection ) );
				vec3 bitangent = cross( outputDirection, tangent );

				// Truncate the kernel at three standard deviations or at the antipode.
				float thetaMax = min( 3.0 * sigma, PI );
				float truncation = 1.0 - exp( - 0.5 * thetaMax * thetaMax / ( sigma * sigma ) );

				vec3 accumColor = vec3( 0.0 );
				float accumWeight = 0.0;

				for ( int i = 0; i < SAMPLES; i ++ ) {

					// Stratified inverse-CDF sampling of the Gaussian, placed on a golden-angle spiral.
					float stratum = ( float( i ) + 0.5 ) / float( SAMPLES );
					float theta = sigma * sqrt( - 2.0 * log( 1.0 - stratum * truncation ) );
					float phi = float( i ) * GOLDEN_ANGLE;

					vec3 offset = cos( phi ) * tangent + sin( phi ) * bitangent;
					vec3 sampleDirection = cos( theta ) * outputDirection + sin( theta ) * offset;

					// Correct the planar sample density to solid angle.
					float weight = sin( theta ) / theta;

					accumColor += weight * bilinearCubeUV( envMap, sampleDirection, mipInt );
					accumWeight += weight;

				}

				gl_FragColor = vec4( accumColor / accumWeight, 1.0 );

			}
		`,blending:0,depthTest:!1,depthWrite:!1})),this._ggxMaterial=(s=a,c=e,d=t,new r.BKk({name:"PMREMGGXConvolution",defines:{GGX_SAMPLES:256,CUBEUV_TEXEL_WIDTH:1/c,CUBEUV_TEXEL_HEIGHT:1/d,CUBEUV_MAX_MIP:"".concat(s,".0")},uniforms:{envMap:{value:null},roughness:{value:0},mipInt:{value:0}},vertexShader:L(),fragmentShader:`

			precision highp float;
			precision highp int;

			varying vec3 vOutputDirection;

			uniform sampler2D envMap;
			uniform float roughness;
			uniform float mipInt;

			#define ENVMAP_TYPE_CUBE_UV
			#include <cube_uv_reflection_fragment>

			#define PI 3.14159265359

			// Van der Corput radical inverse
			float radicalInverse_VdC(uint bits) {
				bits = (bits << 16u) | (bits >> 16u);
				bits = ((bits & 0x55555555u) << 1u) | ((bits & 0xAAAAAAAAu) >> 1u);
				bits = ((bits & 0x33333333u) << 2u) | ((bits & 0xCCCCCCCCu) >> 2u);
				bits = ((bits & 0x0F0F0F0Fu) << 4u) | ((bits & 0xF0F0F0F0u) >> 4u);
				bits = ((bits & 0x00FF00FFu) << 8u) | ((bits & 0xFF00FF00u) >> 8u);
				return float(bits) * 2.3283064365386963e-10; // / 0x100000000
			}

			// Hammersley sequence
			vec2 hammersley(uint i, uint N) {
				return vec2(float(i) / float(N), radicalInverse_VdC(i));
			}

			// GGX VNDF importance sampling (Eric Heitz 2018)
			// "Sampling the GGX Distribution of Visible Normals"
			// https://jcgt.org/published/0007/04/01/
			vec3 importanceSampleGGX_VNDF(vec2 Xi, vec3 V, float roughness) {
				float alpha = roughness * roughness;

				// Section 4.1: Orthonormal basis
				vec3 T1 = vec3(1.0, 0.0, 0.0);
				vec3 T2 = cross(V, T1);

				// Section 4.2: Parameterization of projected area
				float r = sqrt(Xi.x);
				float phi = 2.0 * PI * Xi.y;
				float t1 = r * cos(phi);
				float t2 = r * sin(phi);
				float s = 0.5 * (1.0 + V.z);
				t2 = (1.0 - s) * sqrt(1.0 - t1 * t1) + s * t2;

				// Section 4.3: Reprojection onto hemisphere
				vec3 Nh = t1 * T1 + t2 * T2 + sqrt(max(0.0, 1.0 - t1 * t1 - t2 * t2)) * V;

				// Section 3.4: Transform back to ellipsoid configuration
				return normalize(vec3(alpha * Nh.x, alpha * Nh.y, max(0.0, Nh.z)));
			}

			void main() {
				vec3 N = normalize(vOutputDirection);
				vec3 V = N; // Assume view direction equals normal for pre-filtering

				vec3 prefilteredColor = vec3(0.0);
				float totalWeight = 0.0;

				// For very low roughness, just sample the environment directly
				if (roughness < 0.001) {
					gl_FragColor = vec4(bilinearCubeUV(envMap, N, mipInt), 1.0);
					return;
				}

				// Tangent space basis for VNDF sampling
				vec3 up = abs(N.z) < 0.999 ? vec3(0.0, 0.0, 1.0) : vec3(1.0, 0.0, 0.0);
				vec3 tangent = normalize(cross(up, N));
				vec3 bitangent = cross(N, tangent);

				for(uint i = 0u; i < uint(GGX_SAMPLES); i++) {
					vec2 Xi = hammersley(i, uint(GGX_SAMPLES));

					// For PMREM, V = N, so in tangent space V is always (0, 0, 1)
					vec3 H_tangent = importanceSampleGGX_VNDF(Xi, vec3(0.0, 0.0, 1.0), roughness);

					// Transform H back to world space
					vec3 H = normalize(tangent * H_tangent.x + bitangent * H_tangent.y + N * H_tangent.z);
					vec3 L = normalize(2.0 * dot(V, H) * H - V);

					float NdotL = max(dot(N, L), 0.0);

					if(NdotL > 0.0) {
						// Sample environment at fixed mip level
						// VNDF importance sampling handles the distribution filtering
						vec3 sampleColor = bilinearCubeUV(envMap, L, mipInt);

						// Weight by NdotL for the split-sum approximation
						// VNDF PDF naturally accounts for the visible microfacet distribution
						prefilteredColor += sampleColor * NdotL;
						totalWeight += NdotL;
					}
				}

				if (totalWeight > 0.0) {
					prefilteredColor = prefilteredColor / totalWeight;
				}

				gl_FragColor = vec4(prefilteredColor, 1.0);
			}
		`,blending:0,depthTest:!1,depthWrite:!1}))}return a}_compileMaterial(e){let t=new r.eaF(new r.LoY,e);this._renderer.compile(t,g)}_sceneToCubeUV(e,t,i,a,n){let o=new r.ubm(90,1,t,i),l=[1,-1,1,1,1,1],s=[1,1,1,-1,-1,-1],c=this._renderer,d=c.autoClear,u=c.toneMapping;c.getClearColor(v),c.toneMapping=0,c.autoClear=!1,c.state.buffers.depth.getReversed()&&(c.setRenderTarget(a),c.clearDepth(),c.setRenderTarget(null)),null===this._backgroundBox&&(this._backgroundBox=new r.eaF(new r.iNn,new r.V9B({name:"PMREM.Background",side:1,depthWrite:!1,depthTest:!1})));let f=this._backgroundBox,p=f.material,m=!1,h=e.background;h?h.isColor&&(p.color.copy(h),e.background=null,m=!0):(p.color.copy(v),m=!0);for(let t=0;t<6;t++){let i=t%3;0===i?(o.up.set(0,l[t],0),o.position.set(n.x,n.y,n.z),o.lookAt(n.x+s[t],n.y,n.z)):1===i?(o.up.set(0,0,l[t]),o.position.set(n.x,n.y,n.z),o.lookAt(n.x,n.y+s[t],n.z)):(o.up.set(0,l[t],0),o.position.set(n.x,n.y,n.z),o.lookAt(n.x,n.y,n.z+s[t]));let r=this._cubeSize;b(a,i*r,t>2?r:0,r,r),c.setRenderTarget(a),m&&c.render(f,o),c.render(e,o)}c.toneMapping=u,c.autoClear=d,e.background=h}_textureToCubeUV(e,t){let i=this._renderer,r=301===e.mapping||302===e.mapping;r?(null===this._cubemapMaterial&&(this._cubemapMaterial=P()),this._cubemapMaterial.uniforms.flipEnvMap.value=!1===e.isRenderTargetTexture?-1:1):null===this._equirectMaterial&&(this._equirectMaterial=C());let a=r?this._cubemapMaterial:this._equirectMaterial,n=this._lodMeshes[0];n.material=a,a.uniforms.envMap.value=e;let o=this._cubeSize;b(t,0,0,3*o,2*o),i.setRenderTarget(t),i.render(n,g)}_applyPMREM(e){let t=this._renderer,i=t.autoClear;t.autoClear=!1;let r=this._lodMeshes.length;for(let t=1;t<r;t++)this._applyGGXFilter(e,t-1,t);t.autoClear=i}_applyGGXFilter(e,t,i){let r=this._renderer,a=this._pingPongRenderTarget,n=this._ggxMaterial,o=this._lodMeshes[i];o.material=n;let l=n.uniforms,s=i/(this._lodMeshes.length-1),c=t/(this._lodMeshes.length-1),d=Math.sqrt(s*s-c*c),{_lodMax:u}=this,f=this._sizeLods[i],p=3*f*(i>u-4?i-u+4:0),m=4*(this._cubeSize-f);l.envMap.value=e.texture,l.roughness.value=1.25*s*d,l.mipInt.value=u-t,b(a,p,m,3*f,2*f),r.setRenderTarget(a),r.render(o,g),l.envMap.value=a.texture,l.roughness.value=0,l.mipInt.value=u-i,b(e,p,m,3*f,2*f),r.setRenderTarget(e),r.render(o,g)}_blur(e,t,i,r){let a=this._pingPongRenderTarget,n=Math.min(r,Math.PI)/Math.SQRT2;this._blurPass(e,a,t,i,n),this._blurPass(a,e,i,i,n)}_blurPass(e,t,i,r,a){let n=this._renderer,o=this._blurMaterial,l=this._lodMeshes[r];l.material=o;let s=o.uniforms;s.envMap.value=e.texture,s.sigma.value=a,s.mipInt.value=this._lodMax-i;let c=this._sizeLods[r];b(t,3*c*(r>this._lodMax-4?r-this._lodMax+4:0),4*(this._cubeSize-c),3*c,2*c),n.setRenderTarget(t),n.render(l,g)}constructor(e){this._renderer=e,this._pingPongRenderTarget=null,this._lodMax=0,this._cubeSize=0,this._sizeLods=[],this._lodMeshes=[],this._backgroundBox=null,this._cubemapMaterial=null,this._equirectMaterial=null,this._blurMaterial=null,this._ggxMaterial=null}};function A(e,t,i){let a=new r.nWS(e,t,i);return a.texture.mapping=306,a.texture.name="PMREM.cubeUv",a.scissorTest=!0,a}function b(e,t,i,r,a){e.viewport.set(t,i,r,a),e.scissor.set(t,i,r,a)}function C(){return new r.BKk({name:"EquirectangularToCubeUV",uniforms:{envMap:{value:null}},vertexShader:L(),fragmentShader:`

			precision mediump float;
			precision mediump int;

			varying vec3 vOutputDirection;

			uniform sampler2D envMap;

			#include <common>

			void main() {

				vec3 outputDirection = normalize( vOutputDirection );
				vec2 uv = equirectUv( outputDirection );

				gl_FragColor = vec4( texture2D ( envMap, uv ).rgb, 1.0 );

			}
		`,blending:0,depthTest:!1,depthWrite:!1})}function P(){return new r.BKk({name:"CubemapToCubeUV",uniforms:{envMap:{value:null},flipEnvMap:{value:-1}},vertexShader:L(),fragmentShader:`

			precision mediump float;
			precision mediump int;

			uniform float flipEnvMap;

			varying vec3 vOutputDirection;

			uniform samplerCube envMap;

			void main() {

				gl_FragColor = textureCube( envMap, vec3( flipEnvMap * vOutputDirection.x, vOutputDirection.yz ) );

			}
		`,blending:0,depthTest:!1,depthWrite:!1})}function L(){return`

		precision mediump float;
		precision mediump int;

		attribute vec3 outputDirection;

		varying vec3 vOutputDirection;

		void main() {

			vOutputDirection = outputDirection;
			gl_Position = vec4( position, 1.0 );

		}
	`}let WebGLCubeRenderTarget=class WebGLCubeRenderTarget extends r.nWS{fromEquirectangularTexture(e,t){this.texture.type=t.type,this.texture.colorSpace=t.colorSpace,this.texture.generateMipmaps=t.generateMipmaps,this.texture.minFilter=t.minFilter,this.texture.magFilter=t.magFilter;let i={uniforms:{tEquirect:{value:null}},vertexShader:`

				varying vec3 vWorldDirection;

				vec3 transformDirection( in vec3 dir, in mat4 matrix ) {

					return normalize( ( matrix * vec4( dir, 0.0 ) ).xyz );

				}

				void main() {

					vWorldDirection = transformDirection( position, modelMatrix );

					#include <begin_vertex>
					#include <project_vertex>

				}
			`,fragmentShader:`

				uniform sampler2D tEquirect;

				varying vec3 vWorldDirection;

				#include <common>

				void main() {

					vec3 direction = normalize( vWorldDirection );

					vec2 sampleUV = equirectUv( direction );

					gl_FragColor = texture2D( tEquirect, sampleUV );

				}
			`},a=new r.iNn(5,5,5),n=new r.BKk({name:"CubemapFromEquirect",uniforms:(0,r.lxW)(i.uniforms),vertexShader:i.vertexShader,fragmentShader:i.fragmentShader,side:1,blending:0});n.uniforms.tEquirect.value=t;let o=new r.eaF(a,n),l=t.minFilter;return 1008===t.minFilter&&(t.minFilter=1006),new r.F1T(1,10,this).update(e,o),t.minFilter=l,o.geometry.dispose(),o.material.dispose(),this}clear(e){let t=!(arguments.length>1)||void 0===arguments[1]||arguments[1],i=!(arguments.length>2)||void 0===arguments[2]||arguments[2],r=!(arguments.length>3)||void 0===arguments[3]||arguments[3],a=e.getRenderTarget();for(let a=0;a<6;a++)e.setRenderTarget(this,a),e.clear(t,i,r);e.setRenderTarget(a)}constructor(e=1,t={}){super(e,e,t),this.isWebGLCubeRenderTarget=!0;let i={width:e,height:e,depth:1};this.texture=new r.b4q([i,i,i,i,i,i]),this._setTextureOptions(t),this.texture.isRenderTargetTexture=!0}};function w(e){let t=new WeakMap,i=new WeakMap,r=null;function a(e,t){return 303===t?e.mapping=301:304===t&&(e.mapping=302),e}function n(e){let i=e.target;i.removeEventListener("dispose",n);let r=t.get(i);void 0!==r&&(t.delete(i),r.dispose())}function o(e){let t=e.target;t.removeEventListener("dispose",o);let r=i.get(t);void 0!==r&&(i.delete(t),r.dispose())}return{get:function(l){let s=arguments.length>1&&void 0!==arguments[1]&&arguments[1];return null==l?null:s?function(t){if(t&&t.isTexture){let a=t.mapping,n=303===a||304===a,l=301===a||302===a;if(n||l){let a=i.get(t),s=void 0!==a?a.texture.pmremVersion:0;if(t.isRenderTargetTexture&&t.pmremVersion!==s)return null===r&&(r=new PMREMGenerator(e)),(a=n?r.fromEquirectangular(t,a):r.fromCubemap(t,a)).texture.pmremVersion=t.pmremVersion,i.set(t,a),a.texture;{if(void 0!==a)return a.texture;let s=t.image;return n&&s&&s.height>0||l&&s&&function(e){let t=0;for(let i=0;i<6;i++)void 0!==e[i]&&t++;return 6===t}(s)?(null===r&&(r=new PMREMGenerator(e)),(a=n?r.fromEquirectangular(t):r.fromCubemap(t)).texture.pmremVersion=t.pmremVersion,i.set(t,a),t.addEventListener("dispose",o),a.texture):null}}}return t}(l):function(i){if(i&&i.isTexture){let r=i.mapping;if(303===r||304===r)if(t.has(i))return a(t.get(i).texture,i.mapping);else{let r=i.image;if(!r||!(r.height>0))return null;{let o=new WebGLCubeRenderTarget(r.height);return o.fromEquirectangularTexture(e,i),t.set(i,o),i.addEventListener("dispose",n),a(o.texture,i.mapping)}}}return i}(l)},dispose:function(){t=new WeakMap,i=new WeakMap,null!==r&&(r.dispose(),r=null)}}}function U(e){let t={};function i(i){if(void 0!==t[i])return t[i];let r=e.getExtension(i);return t[i]=r,r}return{has:function(e){return null!==i(e)},init:function(){i("EXT_color_buffer_float"),i("WEBGL_clip_cull_distance"),i("OES_texture_float_linear"),i("EXT_color_buffer_half_float"),i("WEBGL_multisampled_render_to_texture"),i("WEBGL_render_shared_exponent")},get:function(e){let t=i(e);return null===t&&(0,r.mcG)("WebGLRenderer: "+e+" extension not supported."),t}}}function D(e,t,i,a){let n={},o=new WeakMap;function l(e){let r=e.target;for(let e in null!==r.index&&t.remove(r.index),r.attributes)t.remove(r.attributes[e]);r.removeEventListener("dispose",l),delete n[r.id];let s=o.get(r);s&&(t.remove(s),o.delete(r)),a.releaseStatesOfGeometry(r),!0===r.isInstancedBufferGeometry&&delete r._maxInstanceCount,i.memory.geometries--}function s(e){let i=[],a=e.index,n=e.attributes.position,l=0;if(void 0===n)return;if(null!==a){let e=a.array;l=a.version;for(let t=0,r=e.length;t<r;t+=3){let r=e[t+0],a=e[t+1],n=e[t+2];i.push(r,a,a,n,n,r)}}else{let e=n.array;l=n.version;for(let t=0,r=e.length/3-1;t<r;t+=3){let e=t+0,r=t+1,a=t+2;i.push(e,r,r,a,a,e)}}let s=new(n.count>=65535?r.MW4:r.A$4)(i,1);s.version=l;let c=o.get(e);c&&t.remove(c),o.set(e,s)}return{get:function(e,t){return!0===n[t.id]||(t.addEventListener("dispose",l),n[t.id]=!0,i.memory.geometries++),t},update:function(i){let r=i.attributes;for(let i in r)t.update(r[i],e.ARRAY_BUFFER)},getWireframeAttribute:function(e){let t=o.get(e);if(t){let i=e.index;null!==i&&t.version<i.version&&s(e)}else s(e);return o.get(e)}}}function I(e,t,i){let r,a,n;this.setMode=function(e){r=e},this.setIndex=function(e){a=e.type,n=e.bytesPerElement},this.render=function(t,o){e.drawElements(r,o,a,t*n),i.update(o,r,1)},this.renderInstances=function(t,o,l){0!==l&&(e.drawElementsInstanced(r,o,a,t*n,l),i.update(o,r,l))},this.renderMultiDraw=function(e,n,o){if(0===o)return;t.get("WEBGL_multi_draw").multiDrawElementsWEBGL(r,n,0,a,e,0,o);let l=0;for(let e=0;e<o;e++)l+=n[e];i.update(l,r,1)}}function N(e){let t={frame:0,calls:0,triangles:0,points:0,lines:0};return{memory:{geometries:0,textures:0},render:t,programs:null,autoReset:!0,reset:function(){t.calls=0,t.triangles=0,t.points=0,t.lines=0},update:function(i,a,n){switch(t.calls++,a){case e.TRIANGLES:t.triangles+=i/3*n;break;case e.LINES:t.lines+=i/2*n;break;case e.LINE_STRIP:t.lines+=n*(i-1);break;case e.LINE_LOOP:t.lines+=n*i;break;case e.POINTS:t.points+=n*i;break;default:(0,r.z3S)("WebGLInfo: Unknown draw mode:",a)}}}}function y(e,t,i){let a=new WeakMap,n=new r.IUQ;return{update:function(o,l,s){let c=o.morphTargetInfluences,d=l.morphAttributes.position||l.morphAttributes.normal||l.morphAttributes.color,u=void 0!==d?d.length:0,f=a.get(l);if(void 0===f||f.count!==u){void 0!==f&&f.texture.dispose();let e=void 0!==l.morphAttributes.position,i=void 0!==l.morphAttributes.normal,o=void 0!==l.morphAttributes.color,s=l.morphAttributes.position||[],c=l.morphAttributes.normal||[],d=l.morphAttributes.color||[],p=0;!0===e&&(p=1),!0===i&&(p=2),!0===o&&(p=3);let m=l.attributes.position.count*p,h=1;m>t.maxTextureSize&&(h=Math.ceil(m/t.maxTextureSize),m=t.maxTextureSize);let _=new Float32Array(m*h*4*u),g=new r.rFo(_,m,h,u);g.type=1015,g.needsUpdate=!0;let v=4*p;for(let t=0;t<u;t++){let r=s[t],a=c[t],l=d[t],u=m*h*4*t;for(let t=0;t<r.count;t++){let s=t*v;!0===e&&(n.fromBufferAttribute(r,t),_[u+s+0]=n.x,_[u+s+1]=n.y,_[u+s+2]=n.z,_[u+s+3]=0),!0===i&&(n.fromBufferAttribute(a,t),_[u+s+4]=n.x,_[u+s+5]=n.y,_[u+s+6]=n.z,_[u+s+7]=0),!0===o&&(n.fromBufferAttribute(l,t),_[u+s+8]=n.x,_[u+s+9]=n.y,_[u+s+10]=n.z,_[u+s+11]=4===l.itemSize?n.w:1)}}f={count:u,texture:g,size:new r.I9Y(m,h)},a.set(l,f),l.addEventListener("dispose",function e(){g.dispose(),a.delete(l),l.removeEventListener("dispose",e)})}if(!0===o.isInstancedMesh&&null!==o.morphTexture)s.getUniforms().setValue(e,"morphTexture",o.morphTexture,i);else{let t=0;for(let e=0;e<c.length;e++)t+=c[e];let i=l.morphTargetsRelative?1:1-t;s.getUniforms().setValue(e,"morphTargetBaseInfluence",i),s.getUniforms().setValue(e,"morphTargetInfluences",c)}s.getUniforms().setValue(e,"morphTargetsTexture",f.texture,i),s.getUniforms().setValue(e,"morphTargetsTextureSize",f.size)}}}function O(e,t,i,r,a){let n=new WeakMap;function o(e){let t=e.target;t.removeEventListener("dispose",o),r.releaseStatesOfObject(t),i.remove(t.instanceMatrix),null!==t.instanceColor&&i.remove(t.instanceColor)}return{update:function(r){let l=a.render.frame,s=r.geometry,c=t.get(r,s);if(n.get(c)!==l&&(t.update(c),n.set(c,l)),r.isInstancedMesh&&(!1===r.hasEventListener("dispose",o)&&r.addEventListener("dispose",o),n.get(r)!==l&&(i.update(r.instanceMatrix,e.ARRAY_BUFFER),null!==r.instanceColor&&i.update(r.instanceColor,e.ARRAY_BUFFER),n.set(r,l))),r.isSkinnedMesh){let e=r.skeleton;n.get(e)!==l&&(e.update(),n.set(e,l))}return c},dispose:function(){n=new WeakMap}}}let F={1:"LINEAR_TONE_MAPPING",2:"REINHARD_TONE_MAPPING",3:"CINEON_TONE_MAPPING",4:"ACES_FILMIC_TONE_MAPPING",6:"AGX_TONE_MAPPING",7:"NEUTRAL_TONE_MAPPING",5:"CUSTOM_TONE_MAPPING"};function B(e,t,i,a,n,o){let l,s=new r.nWS(t,i,{type:e,depthBuffer:n,stencilBuffer:o,samples:4*!!a,storeMultisampledDepthBuffer:!1,storeMultisampledStencilBuffer:!1,resolveDepthBuffer:!1,resolveStencilBuffer:!1}),c=null,d=null,u=new r.LoY;u.setAttribute("position",new r.qtW([-1,3,0,-1,-1,0,3,-1,0],3)),u.setAttribute("uv",new r.qtW([0,2,0,0,2,0],2));let f=new r.D$Q({uniforms:{tDiffuse:{value:null}},vertexShader:`
			precision highp float;

			uniform mat4 modelViewMatrix;
			uniform mat4 projectionMatrix;

			attribute vec3 position;
			attribute vec2 uv;

			varying vec2 vUv;

			void main() {
				vUv = uv;
				gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );
			}`,fragmentShader:`
			precision highp float;

			uniform sampler2D tDiffuse;

			varying vec2 vUv;

			#include <tonemapping_pars_fragment>
			#include <colorspace_pars_fragment>

			void main() {
				gl_FragColor = texture2D( tDiffuse, vUv );

				#ifdef LINEAR_TONE_MAPPING
					gl_FragColor.rgb = LinearToneMapping( gl_FragColor.rgb );
				#elif defined( REINHARD_TONE_MAPPING )
					gl_FragColor.rgb = ReinhardToneMapping( gl_FragColor.rgb );
				#elif defined( CINEON_TONE_MAPPING )
					gl_FragColor.rgb = CineonToneMapping( gl_FragColor.rgb );
				#elif defined( ACES_FILMIC_TONE_MAPPING )
					gl_FragColor.rgb = ACESFilmicToneMapping( gl_FragColor.rgb );
				#elif defined( AGX_TONE_MAPPING )
					gl_FragColor.rgb = AgXToneMapping( gl_FragColor.rgb );
				#elif defined( NEUTRAL_TONE_MAPPING )
					gl_FragColor.rgb = NeutralToneMapping( gl_FragColor.rgb );
				#elif defined( CUSTOM_TONE_MAPPING )
					gl_FragColor.rgb = CustomToneMapping( gl_FragColor.rgb );
				#endif

				#ifdef SRGB_TRANSFER
					gl_FragColor = sRGBTransferOETF( gl_FragColor );
				#endif
			}`,depthTest:!1,depthWrite:!1}),p=new r.eaF(u,f),m=new r.qUd(-1,1,1,-1,0,1),h=null,_=null,g=!1,v=null,S=[],E=!1;this.setSize=function(e,t){s.setSize(e,t),null!==c&&c.setSize(e,t),null!==d&&d.setSize(e,t);for(let i=0;i<S.length;i++){let r=S[i];r.setSize&&r.setSize(e,t)}},this.setEffects=function(e){E=(S=e).length>0&&!0===S[0].isRenderPass;let t=s.width,i=s.height;S.length>0&&null===c&&(c=new r.nWS(t,i,{type:1016,depthBuffer:!1,stencilBuffer:!1}),d=new r.nWS(t,i,{type:1016,depthBuffer:!1,stencilBuffer:!1}));for(let e=0;e<S.length;e++){let r=S[e];r.setSize&&r.setSize(t,i)}},this.begin=function(e,t){if(g||0===e.toneMapping&&0===S.length)return!1;if(v=t,null!==t){let e=t.width,i=t.height;(s.width!==e||s.height!==i)&&this.setSize(e,i)}return!1===E&&e.setRenderTarget(s),l=e.toneMapping,e.toneMapping=0,!0},this.hasRenderPass=function(){return E},this.end=function(e,t){e.toneMapping=l,g=!0;let i=s,a=c;for(let r=0;r<S.length;r++){let n=S[r];!1!==n.enabled&&(n.render(e,a,i,t),!1!==n.needsSwap&&(i=a,a=a===c?d:c))}if(h!==e.outputColorSpace||_!==e.toneMapping){h=e.outputColorSpace,_=e.toneMapping,f.defines={},"srgb"===r.ppV.getTransfer(h)&&(f.defines.SRGB_TRANSFER="");let t=F[_];t&&(f.defines[t]=""),f.needsUpdate=!0}f.uniforms.tDiffuse.value=i.texture,e.setRenderTarget(v),e.render(p,m),v=null,g=!1},this.isCompositing=function(){return g},this.dispose=function(){s.dispose(),null!==c&&c.dispose(),null!==d&&d.dispose(),u.dispose(),f.dispose()}}let G=new r.gPd,H=new r.VCu(1,1),V=new r.rFo,W=new r.dYF,z=new r.b4q,k=[],X=[],Y=new Float32Array(16),q=new Float32Array(9),K=new Float32Array(4);function j(e,t,i){let r=e[0];if(r<=0||r>0)return e;let a=t*i,n=k[a];if(void 0===n&&(n=new Float32Array(a),k[a]=n),0!==t){r.toArray(n,0);for(let r=1,a=0;r!==t;++r)a+=i,e[r].toArray(n,a)}return n}function Z(e,t){if(e.length!==t.length)return!1;for(let i=0,r=e.length;i<r;i++)if(e[i]!==t[i])return!1;return!0}function Q(e,t){for(let i=0,r=t.length;i<r;i++)e[i]=t[i]}function J(e,t){let i=X[t];void 0===i&&(i=new Int32Array(t),X[t]=i);for(let r=0;r!==t;++r)i[r]=e.allocateTextureUnit();return i}function $(e,t){let i=this.cache;i[0]!==t&&(e.uniform1f(this.addr,t),i[0]=t)}function ee(e,t){let i=this.cache;if(void 0!==t.x)(i[0]!==t.x||i[1]!==t.y)&&(e.uniform2f(this.addr,t.x,t.y),i[0]=t.x,i[1]=t.y);else{if(Z(i,t))return;e.uniform2fv(this.addr,t),Q(i,t)}}function et(e,t){let i=this.cache;if(void 0!==t.x)(i[0]!==t.x||i[1]!==t.y||i[2]!==t.z)&&(e.uniform3f(this.addr,t.x,t.y,t.z),i[0]=t.x,i[1]=t.y,i[2]=t.z);else if(void 0!==t.r)(i[0]!==t.r||i[1]!==t.g||i[2]!==t.b)&&(e.uniform3f(this.addr,t.r,t.g,t.b),i[0]=t.r,i[1]=t.g,i[2]=t.b);else{if(Z(i,t))return;e.uniform3fv(this.addr,t),Q(i,t)}}function ei(e,t){let i=this.cache;if(void 0!==t.x)(i[0]!==t.x||i[1]!==t.y||i[2]!==t.z||i[3]!==t.w)&&(e.uniform4f(this.addr,t.x,t.y,t.z,t.w),i[0]=t.x,i[1]=t.y,i[2]=t.z,i[3]=t.w);else{if(Z(i,t))return;e.uniform4fv(this.addr,t),Q(i,t)}}function er(e,t){let i=this.cache,r=t.elements;if(void 0===r){if(Z(i,t))return;e.uniformMatrix2fv(this.addr,!1,t),Q(i,t)}else{if(Z(i,r))return;K.set(r),e.uniformMatrix2fv(this.addr,!1,K),Q(i,r)}}function ea(e,t){let i=this.cache,r=t.elements;if(void 0===r){if(Z(i,t))return;e.uniformMatrix3fv(this.addr,!1,t),Q(i,t)}else{if(Z(i,r))return;q.set(r),e.uniformMatrix3fv(this.addr,!1,q),Q(i,r)}}function en(e,t){let i=this.cache,r=t.elements;if(void 0===r){if(Z(i,t))return;e.uniformMatrix4fv(this.addr,!1,t),Q(i,t)}else{if(Z(i,r))return;Y.set(r),e.uniformMatrix4fv(this.addr,!1,Y),Q(i,r)}}function eo(e,t){let i=this.cache;i[0]!==t&&(e.uniform1i(this.addr,t),i[0]=t)}function el(e,t){let i=this.cache;if(void 0!==t.x)(i[0]!==t.x||i[1]!==t.y)&&(e.uniform2i(this.addr,t.x,t.y),i[0]=t.x,i[1]=t.y);else{if(Z(i,t))return;e.uniform2iv(this.addr,t),Q(i,t)}}function es(e,t){let i=this.cache;if(void 0!==t.x)(i[0]!==t.x||i[1]!==t.y||i[2]!==t.z)&&(e.uniform3i(this.addr,t.x,t.y,t.z),i[0]=t.x,i[1]=t.y,i[2]=t.z);else{if(Z(i,t))return;e.uniform3iv(this.addr,t),Q(i,t)}}function ec(e,t){let i=this.cache;if(void 0!==t.x)(i[0]!==t.x||i[1]!==t.y||i[2]!==t.z||i[3]!==t.w)&&(e.uniform4i(this.addr,t.x,t.y,t.z,t.w),i[0]=t.x,i[1]=t.y,i[2]=t.z,i[3]=t.w);else{if(Z(i,t))return;e.uniform4iv(this.addr,t),Q(i,t)}}function ed(e,t){let i=this.cache;i[0]!==t&&(e.uniform1ui(this.addr,t),i[0]=t)}function eu(e,t){let i=this.cache;if(void 0!==t.x)(i[0]!==t.x||i[1]!==t.y)&&(e.uniform2ui(this.addr,t.x,t.y),i[0]=t.x,i[1]=t.y);else{if(Z(i,t))return;e.uniform2uiv(this.addr,t),Q(i,t)}}function ef(e,t){let i=this.cache;if(void 0!==t.x)(i[0]!==t.x||i[1]!==t.y||i[2]!==t.z)&&(e.uniform3ui(this.addr,t.x,t.y,t.z),i[0]=t.x,i[1]=t.y,i[2]=t.z);else{if(Z(i,t))return;e.uniform3uiv(this.addr,t),Q(i,t)}}function ep(e,t){let i=this.cache;if(void 0!==t.x)(i[0]!==t.x||i[1]!==t.y||i[2]!==t.z||i[3]!==t.w)&&(e.uniform4ui(this.addr,t.x,t.y,t.z,t.w),i[0]=t.x,i[1]=t.y,i[2]=t.z,i[3]=t.w);else{if(Z(i,t))return;e.uniform4uiv(this.addr,t),Q(i,t)}}function em(e,t,i){let r,a=this.cache,n=i.allocateTextureUnit();a[0]!==n&&(e.uniform1i(this.addr,n),a[0]=n),this.type===e.SAMPLER_2D_SHADOW?(H.compareFunction=i.isReversedDepthBuffer()?518:515,r=H):r=G,i.setTexture2D(t||r,n)}function eh(e,t,i){let r=this.cache,a=i.allocateTextureUnit();r[0]!==a&&(e.uniform1i(this.addr,a),r[0]=a),i.setTexture3D(t||W,a)}function e_(e,t,i){let r=this.cache,a=i.allocateTextureUnit();r[0]!==a&&(e.uniform1i(this.addr,a),r[0]=a),i.setTextureCube(t||z,a)}function eg(e,t,i){let r=this.cache,a=i.allocateTextureUnit();r[0]!==a&&(e.uniform1i(this.addr,a),r[0]=a),i.setTexture2DArray(t||V,a)}function ev(e,t){e.uniform1fv(this.addr,t)}function eS(e,t){let i=j(t,this.size,2);e.uniform2fv(this.addr,i)}function eE(e,t){let i=j(t,this.size,3);e.uniform3fv(this.addr,i)}function eM(e,t){let i=j(t,this.size,4);e.uniform4fv(this.addr,i)}function eT(e,t){let i=j(t,this.size,4);e.uniformMatrix2fv(this.addr,!1,i)}function ex(e,t){let i=j(t,this.size,9);e.uniformMatrix3fv(this.addr,!1,i)}function eR(e,t){let i=j(t,this.size,16);e.uniformMatrix4fv(this.addr,!1,i)}function eA(e,t){e.uniform1iv(this.addr,t)}function eb(e,t){e.uniform2iv(this.addr,t)}function eC(e,t){e.uniform3iv(this.addr,t)}function eP(e,t){e.uniform4iv(this.addr,t)}function eL(e,t){e.uniform1uiv(this.addr,t)}function ew(e,t){e.uniform2uiv(this.addr,t)}function eU(e,t){e.uniform3uiv(this.addr,t)}function eD(e,t){e.uniform4uiv(this.addr,t)}function eI(e,t,i){let r,a=this.cache,n=t.length,o=J(i,n);Z(a,o)||(e.uniform1iv(this.addr,o),Q(a,o)),r=this.type===e.SAMPLER_2D_SHADOW?H:G;for(let e=0;e!==n;++e)i.setTexture2D(t[e]||r,o[e])}function eN(e,t,i){let r=this.cache,a=t.length,n=J(i,a);Z(r,n)||(e.uniform1iv(this.addr,n),Q(r,n));for(let e=0;e!==a;++e)i.setTexture3D(t[e]||W,n[e])}function ey(e,t,i){let r=this.cache,a=t.length,n=J(i,a);Z(r,n)||(e.uniform1iv(this.addr,n),Q(r,n));for(let e=0;e!==a;++e)i.setTextureCube(t[e]||z,n[e])}function eO(e,t,i){let r=this.cache,a=t.length,n=J(i,a);Z(r,n)||(e.uniform1iv(this.addr,n),Q(r,n));for(let e=0;e!==a;++e)i.setTexture2DArray(t[e]||V,n[e])}let SingleUniform=class SingleUniform{constructor(e,t,i){this.id=e,this.addr=i,this.cache=[],this.type=t.type,this.setValue=function(e){switch(e){case 5126:return $;case 35664:return ee;case 35665:return et;case 35666:return ei;case 35674:return er;case 35675:return ea;case 35676:return en;case 5124:case 35670:return eo;case 35667:case 35671:return el;case 35668:case 35672:return es;case 35669:case 35673:return ec;case 5125:return ed;case 36294:return eu;case 36295:return ef;case 36296:return ep;case 35678:case 36198:case 36298:case 36306:case 35682:return em;case 35679:case 36299:case 36307:return eh;case 35680:case 36300:case 36308:case 36293:return e_;case 36289:case 36303:case 36311:case 36292:return eg}}(t.type)}};let PureArrayUniform=class PureArrayUniform{constructor(e,t,i){this.id=e,this.addr=i,this.cache=[],this.type=t.type,this.size=t.size,this.setValue=function(e){switch(e){case 5126:return ev;case 35664:return eS;case 35665:return eE;case 35666:return eM;case 35674:return eT;case 35675:return ex;case 35676:return eR;case 5124:case 35670:return eA;case 35667:case 35671:return eb;case 35668:case 35672:return eC;case 35669:case 35673:return eP;case 5125:return eL;case 36294:return ew;case 36295:return eU;case 36296:return eD;case 35678:case 36198:case 36298:case 36306:case 35682:return eI;case 35679:case 36299:case 36307:return eN;case 35680:case 36300:case 36308:case 36293:return ey;case 36289:case 36303:case 36311:case 36292:return eO}}(t.type)}};let StructuredUniform=class StructuredUniform{setValue(e,t,i){let r=this.seq;for(let a=0,n=r.length;a!==n;++a){let n=r[a];n.setValue(e,t[n.id],i)}}constructor(e){this.id=e,this.seq=[],this.map={}}};let eF=/(\w+)(\])?(\[|\.)?/g;function eB(e,t){e.seq.push(t),e.map[t.id]=t}let WebGLUniforms=class WebGLUniforms{setValue(e,t,i,r){let a=this.map[t];void 0!==a&&a.setValue(e,i,r)}setOptional(e,t,i){let r=t[i];void 0!==r&&this.setValue(e,i,r)}static upload(e,t,i,r){for(let a=0,n=t.length;a!==n;++a){let n=t[a],o=i[n.id];!1!==o.needsUpdate&&n.setValue(e,o.value,r)}}static seqWithValue(e,t){let i=[];for(let r=0,a=e.length;r!==a;++r){let a=e[r];a.id in t&&i.push(a)}return i}constructor(e,t){this.seq=[],this.map={};let i=e.getProgramParameter(t,e.ACTIVE_UNIFORMS);for(let r=0;r<i;++r){let i=e.getActiveUniform(t,r),a=e.getUniformLocation(t,i.name);!function(e,t,i){let r=e.name,a=r.length;for(eF.lastIndex=0;;){let n=eF.exec(r),o=eF.lastIndex,l=n[1],s="]"===n[2],c=n[3];if(s&&(l|=0),void 0===c||"["===c&&o+2===a){eB(i,void 0===c?new SingleUniform(l,e,t):new PureArrayUniform(l,e,t));break}{let e=i.map[l];void 0===e&&eB(i,e=new StructuredUniform(l)),i=e}}}(i,a,this)}let r=[],a=[];for(let t of this.seq)t.type===e.SAMPLER_2D_SHADOW||t.type===e.SAMPLER_CUBE_SHADOW||t.type===e.SAMPLER_2D_ARRAY_SHADOW?r.push(t):a.push(t);r.length>0&&(this.seq=r.concat(a))}};function eG(e,t,i){let r=e.createShader(t);return e.shaderSource(r,i),e.compileShader(r),r}let eH=0,eV=new r.dwI;function eW(e,t,i){let r=e.getShaderParameter(t,e.COMPILE_STATUS),a=(e.getShaderInfoLog(t)||"").trim();if(r&&""===a)return"";let n=/ERROR: 0:(\d+)/.exec(a);if(!n)return a;{let r=parseInt(n[1]);return i.toUpperCase()+`

`+a+`

`+function(e,t){let i=e.split(`
`),r=[],a=Math.max(t-6,0),n=Math.min(t+6,i.length);for(let e=a;e<n;e++){let a=e+1;r.push("".concat(a===t?">":" "," ").concat(a,": ").concat(i[e]))}return r.join(`
`)}(e.getShaderSource(t),r)}}let ez={1:"Linear",2:"Reinhard",3:"Cineon",4:"ACESFilmic",6:"AgX",7:"Neutral",5:"Custom"},ek=new r.Pq0;function eX(e){return""!==e}function eY(e,t){let i=t.numSpotLightShadows+t.numSpotLightMaps-t.numSpotLightShadowsWithMaps;return e.replace(/NUM_SUN_LIGHTS/g,t.numSunLights).replace(/NUM_DIR_LIGHTS/g,t.numDirLights).replace(/NUM_SPOT_LIGHTS/g,t.numSpotLights).replace(/NUM_SPOT_LIGHT_MAPS/g,t.numSpotLightMaps).replace(/NUM_SPOT_LIGHT_COORDS/g,i).replace(/NUM_RECT_AREA_LIGHTS/g,t.numRectAreaLights).replace(/NUM_POINT_LIGHTS/g,t.numPointLights).replace(/NUM_HEMI_LIGHTS/g,t.numHemiLights).replace(/NUM_SUN_LIGHT_SHADOWS/g,t.numSunLightShadows).replace(/NUM_DIR_LIGHT_SHADOWS/g,t.numDirLightShadows).replace(/NUM_SPOT_LIGHT_SHADOWS_WITH_MAPS/g,t.numSpotLightShadowsWithMaps).replace(/NUM_SPOT_LIGHT_SHADOWS/g,t.numSpotLightShadows).replace(/NUM_POINT_LIGHT_SHADOWS/g,t.numPointLightShadows)}function eq(e,t){return e.replace(/NUM_CLIPPING_PLANES/g,t.numClippingPlanes).replace(/UNION_CLIPPING_PLANES/g,t.numClippingPlanes-t.numClipIntersection)}let eK=/^[ \t]*#include +<([\w\d./]+)>/gm;function ej(e){return e.replace(eK,eQ)}let eZ=new Map;function eQ(e,t){let i=o[t];if(void 0===i){let e=eZ.get(t);if(void 0!==e)i=o[e],(0,r.R8M)('WebGLRenderer: Shader chunk "%s" has been deprecated. Use "%s" instead.',t,e);else throw Error("THREE.WebGLProgram: Can not resolve #include <"+t+">")}return ej(i)}let eJ=/#pragma unroll_loop_start\s+for\s*\(\s*int\s+i\s*=\s*(\d+)\s*;\s*i\s*<\s*(\d+)\s*;\s*i\s*\+\+\s*\)\s*{([\s\S]+?)}\s+#pragma unroll_loop_end/g;function e$(e){return e.replace(eJ,e0)}function e0(e,t,i,r){let a="";for(let e=parseInt(t);e<parseInt(i);e++)a+=r.replace(/\[\s*i\s*\]/g,"[ "+e+" ]").replace(/UNROLLED_LOOP_INDEX/g,e);return a}function e1(e){let t="precision ".concat(e.precision,` float;
	precision `).concat(e.precision,` int;
	precision `).concat(e.precision,` sampler2D;
	precision `).concat(e.precision,` samplerCube;
	precision `).concat(e.precision,` sampler3D;
	precision `).concat(e.precision,` sampler2DArray;
	precision `).concat(e.precision,` sampler2DShadow;
	precision `).concat(e.precision,` samplerCubeShadow;
	precision `).concat(e.precision,` sampler2DArrayShadow;
	precision `).concat(e.precision,` isampler2D;
	precision `).concat(e.precision,` isampler3D;
	precision `).concat(e.precision,` isamplerCube;
	precision `).concat(e.precision,` isampler2DArray;
	precision `).concat(e.precision,` usampler2D;
	precision `).concat(e.precision,` usampler3D;
	precision `).concat(e.precision,` usamplerCube;
	precision `).concat(e.precision,` usampler2DArray;
	`);return"highp"===e.precision?t+=`
#define HIGH_PRECISION`:"mediump"===e.precision?t+=`
#define MEDIUM_PRECISION`:"lowp"===e.precision&&(t+=`
#define LOW_PRECISION`),t}let e3={1:"SHADOWMAP_TYPE_PCF",3:"SHADOWMAP_TYPE_VSM"},e2={301:"ENVMAP_TYPE_CUBE",302:"ENVMAP_TYPE_CUBE",306:"ENVMAP_TYPE_CUBE_UV"},e4={302:"ENVMAP_MODE_REFRACTION"},e5={0:"ENVMAP_BLENDING_MULTIPLY",1:"ENVMAP_BLENDING_MIX",2:"ENVMAP_BLENDING_ADD"};function e6(e,t,i,a){var n,l;let s,c,d,u,f=e.getContext(),p=i.defines,m=i.vertexShader,h=i.fragmentShader,_=e3[i.shadowMapType]||"SHADOWMAP_TYPE_BASIC",g=!1===i.envMap?"ENVMAP_TYPE_CUBE":e2[i.envMapMode]||"ENVMAP_TYPE_CUBE",v=!1===i.envMap?"ENVMAP_MODE_REFLECTION":e4[i.envMapMode]||"ENVMAP_MODE_REFLECTION",S=!1===i.envMap?"ENVMAP_BLENDING_NONE":e5[i.combine]||"ENVMAP_BLENDING_NONE",E=function(e){let t=e.envMapCubeUVHeight;if(null===t)return null;let i=Math.log2(t)-2;return{texelWidth:1/(3*Math.max(Math.pow(2,i),112)),texelHeight:1/t,maxMip:i}}(i),M=[i.extensionClipCullDistance?"#extension GL_ANGLE_clip_cull_distance : require":"",i.extensionMultiDraw?"#extension GL_ANGLE_multi_draw : require":""].filter(eX).join(`
`),T=function(e){let t=[];for(let i in e){let r=e[i];!1!==r&&t.push("#define "+i+" "+r)}return t.join(`
`)}(p),x=f.createProgram(),R=i.glslVersion?"#version "+i.glslVersion+`
`:"";if(i.isRawShaderMaterial)(s=["#define SHADER_TYPE "+i.shaderType,"#define SHADER_NAME "+i.shaderName,T].filter(eX).join(`
`)).length>0&&(s+=`
`),(c=["#define SHADER_TYPE "+i.shaderType,"#define SHADER_NAME "+i.shaderName,T].filter(eX).join(`
`)).length>0&&(c+=`
`);else{let e,t,a,d,u;s=[e1(i),"#define SHADER_TYPE "+i.shaderType,"#define SHADER_NAME "+i.shaderName,T,i.extensionClipCullDistance?"#define USE_CLIP_DISTANCE":"",i.batching?"#define USE_BATCHING":"",i.batchingColor?"#define USE_BATCHING_COLOR":"",i.instancing?"#define USE_INSTANCING":"",i.instancingColor?"#define USE_INSTANCING_COLOR":"",i.instancingMorph?"#define USE_INSTANCING_MORPH":"",i.useFog&&i.fog?"#define USE_FOG":"",i.useFog&&i.fogExp2?"#define FOG_EXP2":"",i.map?"#define USE_MAP":"",i.envMap?"#define USE_ENVMAP":"",i.envMap?"#define "+v:"",i.lightMap?"#define USE_LIGHTMAP":"",i.aoMap?"#define USE_AOMAP":"",i.bumpMap?"#define USE_BUMPMAP":"",i.normalMap?"#define USE_NORMALMAP":"",i.normalMapObjectSpace?"#define USE_NORMALMAP_OBJECTSPACE":"",i.normalMapTangentSpace?"#define USE_NORMALMAP_TANGENTSPACE":"",i.displacementMap?"#define USE_DISPLACEMENTMAP":"",i.emissiveMap?"#define USE_EMISSIVEMAP":"",i.anisotropy?"#define USE_ANISOTROPY":"",i.anisotropyMap?"#define USE_ANISOTROPYMAP":"",i.clearcoatMap?"#define USE_CLEARCOATMAP":"",i.clearcoatRoughnessMap?"#define USE_CLEARCOAT_ROUGHNESSMAP":"",i.clearcoatNormalMap?"#define USE_CLEARCOAT_NORMALMAP":"",i.iridescenceMap?"#define USE_IRIDESCENCEMAP":"",i.iridescenceThicknessMap?"#define USE_IRIDESCENCE_THICKNESSMAP":"",i.specularMap?"#define USE_SPECULARMAP":"",i.specularColorMap?"#define USE_SPECULAR_COLORMAP":"",i.specularIntensityMap?"#define USE_SPECULAR_INTENSITYMAP":"",i.roughnessMap?"#define USE_ROUGHNESSMAP":"",i.metalnessMap?"#define USE_METALNESSMAP":"",i.alphaMap?"#define USE_ALPHAMAP":"",i.alphaHash?"#define USE_ALPHAHASH":"",i.transmission?"#define USE_TRANSMISSION":"",i.transmissionMap?"#define USE_TRANSMISSIONMAP":"",i.thicknessMap?"#define USE_THICKNESSMAP":"",i.sheenColorMap?"#define USE_SHEEN_COLORMAP":"",i.sheenRoughnessMap?"#define USE_SHEEN_ROUGHNESSMAP":"",i.mapUv?"#define MAP_UV "+i.mapUv:"",i.alphaMapUv?"#define ALPHAMAP_UV "+i.alphaMapUv:"",i.lightMapUv?"#define LIGHTMAP_UV "+i.lightMapUv:"",i.aoMapUv?"#define AOMAP_UV "+i.aoMapUv:"",i.emissiveMapUv?"#define EMISSIVEMAP_UV "+i.emissiveMapUv:"",i.bumpMapUv?"#define BUMPMAP_UV "+i.bumpMapUv:"",i.normalMapUv?"#define NORMALMAP_UV "+i.normalMapUv:"",i.displacementMapUv?"#define DISPLACEMENTMAP_UV "+i.displacementMapUv:"",i.metalnessMapUv?"#define METALNESSMAP_UV "+i.metalnessMapUv:"",i.roughnessMapUv?"#define ROUGHNESSMAP_UV "+i.roughnessMapUv:"",i.anisotropyMapUv?"#define ANISOTROPYMAP_UV "+i.anisotropyMapUv:"",i.clearcoatMapUv?"#define CLEARCOATMAP_UV "+i.clearcoatMapUv:"",i.clearcoatNormalMapUv?"#define CLEARCOAT_NORMALMAP_UV "+i.clearcoatNormalMapUv:"",i.clearcoatRoughnessMapUv?"#define CLEARCOAT_ROUGHNESSMAP_UV "+i.clearcoatRoughnessMapUv:"",i.iridescenceMapUv?"#define IRIDESCENCEMAP_UV "+i.iridescenceMapUv:"",i.iridescenceThicknessMapUv?"#define IRIDESCENCE_THICKNESSMAP_UV "+i.iridescenceThicknessMapUv:"",i.sheenColorMapUv?"#define SHEEN_COLORMAP_UV "+i.sheenColorMapUv:"",i.sheenRoughnessMapUv?"#define SHEEN_ROUGHNESSMAP_UV "+i.sheenRoughnessMapUv:"",i.specularMapUv?"#define SPECULARMAP_UV "+i.specularMapUv:"",i.specularColorMapUv?"#define SPECULAR_COLORMAP_UV "+i.specularColorMapUv:"",i.specularIntensityMapUv?"#define SPECULAR_INTENSITYMAP_UV "+i.specularIntensityMapUv:"",i.transmissionMapUv?"#define TRANSMISSIONMAP_UV "+i.transmissionMapUv:"",i.thicknessMapUv?"#define THICKNESSMAP_UV "+i.thicknessMapUv:"",i.vertexTangents&&!1===i.flatShading?"#define USE_TANGENT":"",i.vertexNormals?"#define HAS_NORMAL":"",i.vertexColors?"#define USE_COLOR":"",i.vertexAlphas?"#define USE_COLOR_ALPHA":"",i.vertexUv1s?"#define USE_UV1":"",i.vertexUv2s?"#define USE_UV2":"",i.vertexUv3s?"#define USE_UV3":"",i.pointsUvs?"#define USE_POINTS_UV":"",i.flatShading?"#define FLAT_SHADED":"",i.skinning?"#define USE_SKINNING":"",i.morphTargets?"#define USE_MORPHTARGETS":"",i.morphNormals&&!1===i.flatShading?"#define USE_MORPHNORMALS":"",i.morphColors?"#define USE_MORPHCOLORS":"",i.morphTargetsCount>0?"#define MORPHTARGETS_TEXTURE_STRIDE "+i.morphTextureStride:"",i.morphTargetsCount>0?"#define MORPHTARGETS_COUNT "+i.morphTargetsCount:"",i.doubleSided?"#define DOUBLE_SIDED":"",i.flipSided?"#define FLIP_SIDED":"",i.shadowMapEnabled?"#define USE_SHADOWMAP":"",i.shadowMapEnabled?"#define "+_:"",i.sizeAttenuation?"#define USE_SIZEATTENUATION":"",i.numLightProbes>0?"#define USE_LIGHT_PROBES":"",i.logarithmicDepthBuffer?"#define USE_LOGARITHMIC_DEPTH_BUFFER":"",i.reversedDepthBuffer?"#define USE_REVERSED_DEPTH_BUFFER":"","uniform mat4 modelMatrix;","uniform mat4 modelViewMatrix;","uniform mat4 projectionMatrix;","uniform mat4 viewMatrix;","uniform mat3 normalMatrix;","uniform vec3 cameraPosition;","uniform bool isOrthographic;","#ifdef USE_INSTANCING","	attribute mat4 instanceMatrix;","#endif","#ifdef USE_INSTANCING_COLOR","	attribute vec3 instanceColor;","#endif","#ifdef USE_INSTANCING_MORPH","	uniform sampler2D morphTexture;","#endif","attribute vec3 position;","attribute vec3 normal;","attribute vec2 uv;","#ifdef USE_UV1","	attribute vec2 uv1;","#endif","#ifdef USE_UV2","	attribute vec2 uv2;","#endif","#ifdef USE_UV3","	attribute vec2 uv3;","#endif","#ifdef USE_TANGENT","	attribute vec4 tangent;","#endif","#if defined( USE_COLOR_ALPHA )","	attribute vec4 color;","#elif defined( USE_COLOR )","	attribute vec3 color;","#endif","#ifdef USE_SKINNING","	attribute vec4 skinIndex;","	attribute vec4 skinWeight;","#endif",`
`].filter(eX).join(`
`),c=[e1(i),"#define SHADER_TYPE "+i.shaderType,"#define SHADER_NAME "+i.shaderName,T,i.useFog&&i.fog?"#define USE_FOG":"",i.useFog&&i.fogExp2?"#define FOG_EXP2":"",i.alphaToCoverage?"#define ALPHA_TO_COVERAGE":"",i.map?"#define USE_MAP":"",i.matcap?"#define USE_MATCAP":"",i.envMap?"#define USE_ENVMAP":"",i.envMap?"#define "+g:"",i.envMap?"#define "+v:"",i.envMap?"#define "+S:"",E?"#define CUBEUV_TEXEL_WIDTH "+E.texelWidth:"",E?"#define CUBEUV_TEXEL_HEIGHT "+E.texelHeight:"",E?"#define CUBEUV_MAX_MIP "+E.maxMip+".0":"",i.lightMap?"#define USE_LIGHTMAP":"",i.aoMap?"#define USE_AOMAP":"",i.bumpMap?"#define USE_BUMPMAP":"",i.normalMap?"#define USE_NORMALMAP":"",i.normalMapObjectSpace?"#define USE_NORMALMAP_OBJECTSPACE":"",i.normalMapTangentSpace?"#define USE_NORMALMAP_TANGENTSPACE":"",i.packedNormalMap?"#define USE_PACKED_NORMALMAP":"",i.emissiveMap?"#define USE_EMISSIVEMAP":"",i.anisotropy?"#define USE_ANISOTROPY":"",i.anisotropyMap?"#define USE_ANISOTROPYMAP":"",i.clearcoat?"#define USE_CLEARCOAT":"",i.clearcoatMap?"#define USE_CLEARCOATMAP":"",i.clearcoatRoughnessMap?"#define USE_CLEARCOAT_ROUGHNESSMAP":"",i.clearcoatNormalMap?"#define USE_CLEARCOAT_NORMALMAP":"",i.dispersion?"#define USE_DISPERSION":"",i.retroreflection?"#define USE_RETROREFLECTION":"",i.iridescence?"#define USE_IRIDESCENCE":"",i.iridescenceMap?"#define USE_IRIDESCENCEMAP":"",i.iridescenceThicknessMap?"#define USE_IRIDESCENCE_THICKNESSMAP":"",i.specularMap?"#define USE_SPECULARMAP":"",i.specularColorMap?"#define USE_SPECULAR_COLORMAP":"",i.specularIntensityMap?"#define USE_SPECULAR_INTENSITYMAP":"",i.roughnessMap?"#define USE_ROUGHNESSMAP":"",i.metalnessMap?"#define USE_METALNESSMAP":"",i.alphaMap?"#define USE_ALPHAMAP":"",i.alphaTest?"#define USE_ALPHATEST":"",i.alphaHash?"#define USE_ALPHAHASH":"",i.sheen?"#define USE_SHEEN":"",i.sheenColorMap?"#define USE_SHEEN_COLORMAP":"",i.sheenRoughnessMap?"#define USE_SHEEN_ROUGHNESSMAP":"",i.transmission?"#define USE_TRANSMISSION":"",i.transmissionMap?"#define USE_TRANSMISSIONMAP":"",i.thicknessMap?"#define USE_THICKNESSMAP":"",i.vertexTangents&&!1===i.flatShading?"#define USE_TANGENT":"",i.vertexColors||i.instancingColor?"#define USE_COLOR":"",i.vertexAlphas||i.batchingColor?"#define USE_COLOR_ALPHA":"",i.vertexUv1s?"#define USE_UV1":"",i.vertexUv2s?"#define USE_UV2":"",i.vertexUv3s?"#define USE_UV3":"",i.pointsUvs?"#define USE_POINTS_UV":"",i.gradientMap?"#define USE_GRADIENTMAP":"",i.flatShading?"#define FLAT_SHADED":"",i.doubleSided?"#define DOUBLE_SIDED":"",i.flipSided?"#define FLIP_SIDED":"",i.shadowMapEnabled?"#define USE_SHADOWMAP":"",i.shadowMapEnabled?"#define "+_:"",i.premultipliedAlpha?"#define PREMULTIPLIED_ALPHA":"",i.numLightProbes>0?"#define USE_LIGHT_PROBES":"",i.numLightProbeGrids>0?"#define USE_LIGHT_PROBES_GRID":"",i.decodeVideoTexture?"#define DECODE_VIDEO_TEXTURE":"",i.decodeVideoTextureEmissive?"#define DECODE_VIDEO_TEXTURE_EMISSIVE":"",i.logarithmicDepthBuffer?"#define USE_LOGARITHMIC_DEPTH_BUFFER":"",i.reversedDepthBuffer?"#define USE_REVERSED_DEPTH_BUFFER":"","uniform mat4 viewMatrix;","uniform vec3 cameraPosition;","uniform bool isOrthographic;",0!==i.toneMapping?"#define TONE_MAPPING":"",0!==i.toneMapping?o.tonemapping_pars_fragment:"",0!==i.toneMapping?(n="toneMapping",void 0===(e=ez[l=i.toneMapping])?((0,r.R8M)("WebGLProgram: Unsupported toneMapping:",l),"vec3 "+n+"( vec3 color ) { return LinearToneMapping( color ); }"):"vec3 "+n+"( vec3 color ) { return "+e+"ToneMapping( color ); }"):"",i.dithering?"#define DITHERING":"",i.opaque?"#define OPAQUE":"",o.colorspace_pars_fragment,(t=function(e){r.ppV._getMatrix(eV,r.ppV.workingColorSpace,e);let t="mat3( ".concat(eV.elements.map(e=>e.toFixed(4))," )");switch(r.ppV.getTransfer(e)){case"linear":return[t,"LinearTransferOETF"];case"srgb":return[t,"sRGBTransferOETF"];default:return(0,r.R8M)("WebGLProgram: Unsupported color space: ",e),[t,"LinearTransferOETF"]}}(i.outputColorSpace),["vec4 ".concat("linearToOutputTexel","( vec4 value ) {"),"	return ".concat(t[1],"( vec4( value.rgb * ").concat(t[0],", value.a ) );"),"}"].join(`
`)),(r.ppV.getLuminanceCoefficients(ek),a=ek.x.toFixed(4),d=ek.y.toFixed(4),u=ek.z.toFixed(4),["float luminance( const in vec3 rgb ) {","	const vec3 weights = vec3( ".concat(a,", ").concat(d,", ").concat(u," );"),`	return dot( weights, rgb );
}`].join(`
`)),i.useDepthPacking?"#define DEPTH_PACKING "+i.depthPacking:"",`
`].filter(eX).join(`
`)}m=eq(m=eY(m=ej(m),i),i),h=eq(h=eY(h=ej(h),i),i),m=e$(m),h=e$(h),!0!==i.isRawShaderMaterial&&(R=`#version 300 es
`,s=[M,`#define attribute in
#define varying out
#define texture2D texture`].join(`
`)+`
`+s,c=["#define varying in","300 es"===i.glslVersion?"":"layout(location = 0) out highp vec4 pc_fragColor;","300 es"===i.glslVersion?"":"#define gl_FragColor pc_fragColor",`#define gl_FragDepthEXT gl_FragDepth
#define texture2D texture
#define textureCube texture
#define texture2DProj textureProj
#define texture2DLodEXT textureLod
#define texture2DProjLodEXT textureProjLod
#define textureCubeLodEXT textureLod
#define texture2DGradEXT textureGrad
#define texture2DProjGradEXT textureProjGrad
#define textureCubeGradEXT textureGrad`].join(`
`)+`
`+c);let A=R+s+m,b=R+c+h,C=eG(f,f.VERTEX_SHADER,A),P=eG(f,f.FRAGMENT_SHADER,b);function L(t){if(e.debug.checkShaderErrors){let i=f.getProgramInfoLog(x)||"",a=f.getShaderInfoLog(C)||"",n=f.getShaderInfoLog(P)||"",o=i.trim(),l=a.trim(),d=n.trim(),u=!0,p=!0;if(!1===f.getProgramParameter(x,f.LINK_STATUS))if(u=!1,"function"==typeof e.debug.onShaderError)e.debug.onShaderError(f,x,C,P);else{let e=eW(f,C,"vertex"),i=eW(f,P,"fragment");(0,r.z3S)("WebGLProgram: Shader Error "+f.getError()+" - VALIDATE_STATUS "+f.getProgramParameter(x,f.VALIDATE_STATUS)+`

Material Name: `+t.name+`
Material Type: `+t.type+`

Program Info Log: `+o+`
`+e+`
`+i)}else""!==o?(0,r.R8M)("WebGLProgram: Program Info Log:",o):(""===l||""===d)&&(p=!1);p&&(t.diagnostics={runnable:u,programLog:o,vertexShader:{log:l,prefix:s},fragmentShader:{log:d,prefix:c}})}f.deleteShader(C),f.deleteShader(P),d=new WebGLUniforms(f,x),u=function(e,t){let i={},r=e.getProgramParameter(t,e.ACTIVE_ATTRIBUTES);for(let a=0;a<r;a++){let r=e.getActiveAttrib(t,a),n=r.name,o=1;r.type===e.FLOAT_MAT2&&(o=2),r.type===e.FLOAT_MAT3&&(o=3),r.type===e.FLOAT_MAT4&&(o=4),i[n]={type:r.type,location:e.getAttribLocation(t,n),locationSize:o}}return i}(f,x)}f.attachShader(x,C),f.attachShader(x,P),void 0!==i.index0AttributeName?f.bindAttribLocation(x,0,i.index0AttributeName):!0===i.hasPositionAttribute&&f.bindAttribLocation(x,0,"position"),f.linkProgram(x),this.getUniforms=function(){return void 0===d&&L(this),d},this.getAttributes=function(){return void 0===u&&L(this),u};let w=!1===i.rendererExtensionParallelShaderCompile;return this.isReady=function(){return!1===w&&(w=f.getProgramParameter(x,37297)),w},this.destroy=function(){a.releaseStatesOfProgram(this),f.deleteProgram(x),this.program=void 0},this.type=i.shaderType,this.name=i.shaderName,this.id=eH++,this.cacheKey=t,this.usedTimes=1,this.program=x,this.vertexShader=C,this.fragmentShader=P,this}let e8=0;let WebGLShaderCache=class WebGLShaderCache{update(e,t,i){let r=this._getShaderCacheForMaterial(e);return!1===r.has(t)&&(r.add(t),t.usedTimes++),!1===r.has(i)&&(r.add(i),i.usedTimes++),this}remove(e){for(let t of this.materialCache.get(e))t.usedTimes--,0===t.usedTimes&&this.shaderCache.delete(t.code);return this.materialCache.delete(e),this}getVertexShaderStage(e){return this._getShaderStage(e.vertexShader)}getFragmentShaderStage(e){return this._getShaderStage(e.fragmentShader)}dispose(){this.shaderCache.clear(),this.materialCache.clear()}_getShaderCacheForMaterial(e){let t=this.materialCache,i=t.get(e);return void 0===i&&(i=new Set,t.set(e,i)),i}_getShaderStage(e){let t=this.shaderCache,i=t.get(e);return void 0===i&&(i=new WebGLShaderStage(e),t.set(e,i)),i}constructor(){this.shaderCache=new Map,this.materialCache=new Map}};let WebGLShaderStage=class WebGLShaderStage{constructor(e){this.id=e8++,this.code=e,this.usedTimes=0}};function e9(e,t,i,a,n,o){let l=new r.zgK,c=new WebGLShaderCache,d=new Set,u=[],f=new Map,p=a.logarithmicDepthBuffer,m=a.precision,h={MeshDepthMaterial:"depth",MeshDistanceMaterial:"distance",MeshNormalMaterial:"normal",MeshBasicMaterial:"basic",MeshLambertMaterial:"lambert",MeshPhongMaterial:"phong",MeshToonMaterial:"toon",MeshStandardMaterial:"physical",MeshPhysicalMaterial:"physical",MeshMatcapMaterial:"matcap",LineBasicMaterial:"basic",LineDashedMaterial:"dashed",PointsMaterial:"points",ShadowMaterial:"shadow",SpriteMaterial:"sprite"};function _(e){return(d.add(e),0===e)?"uv":"uv".concat(e)}return{getParameters:function(n,l,u,f,g,v){var S;let E,M,T,x,R=f.fog,A=g.geometry,b=n.isMeshStandardMaterial||n.isMeshLambertMaterial||n.isMeshPhongMaterial?f.environment:null,C=n.isMeshStandardMaterial||n.isMeshLambertMaterial&&!n.envMap||n.isMeshPhongMaterial&&!n.envMap,P=t.get(n.envMap||b,C),L=P&&306===P.mapping?P.image.height:null,w=h[n.type];null!==n.precision&&(m=a.getMaxPrecision(n.precision))!==n.precision&&(0,r.R8M)("WebGLProgram.getParameters:",n.precision,"not supported, using",m,"instead.");let U=A.morphAttributes.position||A.morphAttributes.normal||A.morphAttributes.color,D=void 0!==U?U.length:0,I=0;if(void 0!==A.morphAttributes.position&&(I=1),void 0!==A.morphAttributes.normal&&(I=2),void 0!==A.morphAttributes.color&&(I=3),w){let e=s[w];E=e.vertexShader,M=e.fragmentShader}else{E=n.vertexShader,M=n.fragmentShader;let e=c.getVertexShaderStage(n),t=c.getFragmentShaderStage(n);c.update(n,e,t),T=e.id,x=t.id}let N=e.getRenderTarget(),y=e.state.buffers.depth.getReversed(),O=!0===g.isInstancedMesh,F=!0===g.isBatchedMesh,B=!!n.map,G=!!n.matcap,H=!!P,V=!!n.aoMap,W=!!n.lightMap,z=!!n.bumpMap&&!1===n.wireframe,k=!!n.normalMap,X=!!n.displacementMap,Y=!!n.emissiveMap,q=!!n.metalnessMap,K=!!n.roughnessMap,j=n.anisotropy>0,Z=n.clearcoat>0,Q=n.dispersion>0,J=n.retroreflectivity>0,$=n.iridescence>0,ee=n.sheen>0,et=n.transmission>0,ei=j&&!!n.anisotropyMap,er=Z&&!!n.clearcoatMap,ea=Z&&!!n.clearcoatNormalMap,en=Z&&!!n.clearcoatRoughnessMap,eo=$&&!!n.iridescenceMap,el=$&&!!n.iridescenceThicknessMap,es=ee&&!!n.sheenColorMap,ec=ee&&!!n.sheenRoughnessMap,ed=!!n.specularMap,eu=!!n.specularColorMap,ef=!!n.specularIntensityMap,ep=et&&!!n.transmissionMap,em=et&&!!n.thicknessMap,eh=!!n.gradientMap,e_=!!n.alphaMap,eg=n.alphaTest>0,ev=!!n.alphaHash,eS=!!n.extensions,eE=0;n.toneMapped&&(null===N||!0===N.isXRRenderTarget)&&(eE=e.toneMapping);let eM={shaderID:w,shaderType:n.type,shaderName:n.name,vertexShader:E,fragmentShader:M,defines:n.defines,customVertexShaderID:T,customFragmentShaderID:x,isRawShaderMaterial:!0===n.isRawShaderMaterial,glslVersion:n.glslVersion,precision:m,batching:F,batchingColor:F&&null!==g._colorsTexture,instancing:O,instancingColor:O&&null!==g.instanceColor,instancingMorph:O&&null!==g.morphTexture,outputColorSpace:null===N?e.outputColorSpace:!0===N.isXRRenderTarget?N.texture.colorSpace:r.ppV.workingColorSpace,alphaToCoverage:!!n.alphaToCoverage,map:B,matcap:G,envMap:H,envMapMode:H&&P.mapping,envMapCubeUVHeight:L,aoMap:V,lightMap:W,bumpMap:z,normalMap:k,displacementMap:X,emissiveMap:Y,normalMapObjectSpace:k&&1===n.normalMapType,normalMapTangentSpace:k&&0===n.normalMapType,packedNormalMap:k&&0===n.normalMapType&&(1030===(S=n.normalMap.format)||37490===S||36285===S),metalnessMap:q,roughnessMap:K,anisotropy:j,anisotropyMap:ei,clearcoat:Z,clearcoatMap:er,clearcoatNormalMap:ea,clearcoatRoughnessMap:en,dispersion:Q,retroreflection:J,iridescence:$,iridescenceMap:eo,iridescenceThicknessMap:el,sheen:ee,sheenColorMap:es,sheenRoughnessMap:ec,specularMap:ed,specularColorMap:eu,specularIntensityMap:ef,transmission:et,transmissionMap:ep,thicknessMap:em,gradientMap:eh,opaque:!1===n.transparent&&1===n.blending&&!1===n.alphaToCoverage,alphaMap:e_,alphaTest:eg,alphaHash:ev,combine:n.combine,mapUv:B&&_(n.map.channel),aoMapUv:V&&_(n.aoMap.channel),lightMapUv:W&&_(n.lightMap.channel),bumpMapUv:z&&_(n.bumpMap.channel),normalMapUv:k&&_(n.normalMap.channel),displacementMapUv:X&&_(n.displacementMap.channel),emissiveMapUv:Y&&_(n.emissiveMap.channel),metalnessMapUv:q&&_(n.metalnessMap.channel),roughnessMapUv:K&&_(n.roughnessMap.channel),anisotropyMapUv:ei&&_(n.anisotropyMap.channel),clearcoatMapUv:er&&_(n.clearcoatMap.channel),clearcoatNormalMapUv:ea&&_(n.clearcoatNormalMap.channel),clearcoatRoughnessMapUv:en&&_(n.clearcoatRoughnessMap.channel),iridescenceMapUv:eo&&_(n.iridescenceMap.channel),iridescenceThicknessMapUv:el&&_(n.iridescenceThicknessMap.channel),sheenColorMapUv:es&&_(n.sheenColorMap.channel),sheenRoughnessMapUv:ec&&_(n.sheenRoughnessMap.channel),specularMapUv:ed&&_(n.specularMap.channel),specularColorMapUv:eu&&_(n.specularColorMap.channel),specularIntensityMapUv:ef&&_(n.specularIntensityMap.channel),transmissionMapUv:ep&&_(n.transmissionMap.channel),thicknessMapUv:em&&_(n.thicknessMap.channel),alphaMapUv:e_&&_(n.alphaMap.channel),vertexTangents:!!A.attributes.tangent&&(k||j),vertexNormals:!!A.attributes.normal,vertexColors:n.vertexColors,vertexAlphas:!0===n.vertexColors&&!!A.attributes.color&&4===A.attributes.color.itemSize,pointsUvs:!0===g.isPoints&&!!A.attributes.uv&&(B||e_),fog:!!R,useFog:!0===n.fog,fogExp2:!!R&&R.isFogExp2,flatShading:!1===n.wireframe&&(!0===n.flatShading||void 0===A.attributes.normal&&!1===k&&(n.isMeshLambertMaterial||n.isMeshPhongMaterial||n.isMeshStandardMaterial||n.isMeshPhysicalMaterial)),sizeAttenuation:!0===n.sizeAttenuation,logarithmicDepthBuffer:p,reversedDepthBuffer:y,skinning:!0===g.isSkinnedMesh,hasPositionAttribute:void 0!==A.attributes.position,morphTargets:void 0!==A.morphAttributes.position,morphNormals:void 0!==A.morphAttributes.normal,morphColors:void 0!==A.morphAttributes.color,morphTargetsCount:D,morphTextureStride:I,numSunLights:l.sun.length,numDirLights:l.directional.length,numPointLights:l.point.length,numSpotLights:l.spot.length,numSpotLightMaps:l.spotLightMap.length,numRectAreaLights:l.rectArea.length,numHemiLights:l.hemi.length,numSunLightShadows:l.sunShadowMap.length,numDirLightShadows:l.directionalShadowMap.length,numPointLightShadows:l.pointShadowMap.length,numSpotLightShadows:l.spotShadowMap.length,numSpotLightShadowsWithMaps:l.numSpotLightShadowsWithMaps,numLightProbes:l.numLightProbes,numLightProbeGrids:v.length,numClippingPlanes:o.numPlanes,numClipIntersection:o.numIntersection,dithering:n.dithering,shadowMapEnabled:e.shadowMap.enabled&&u.length>0,shadowMapType:e.shadowMap.type,toneMapping:eE,decodeVideoTexture:B&&!0===n.map.isVideoTexture&&"srgb"===r.ppV.getTransfer(n.map.colorSpace),decodeVideoTextureEmissive:Y&&!0===n.emissiveMap.isVideoTexture&&"srgb"===r.ppV.getTransfer(n.emissiveMap.colorSpace),premultipliedAlpha:n.premultipliedAlpha,doubleSided:2===n.side,flipSided:1===n.side,useDepthPacking:n.depthPacking>=0,depthPacking:n.depthPacking||0,index0AttributeName:n.index0AttributeName,extensionClipCullDistance:eS&&!0===n.extensions.clipCullDistance&&i.has("WEBGL_clip_cull_distance"),extensionMultiDraw:(eS&&!0===n.extensions.multiDraw||F)&&i.has("WEBGL_multi_draw"),rendererExtensionParallelShaderCompile:i.has("KHR_parallel_shader_compile"),customProgramCacheKey:n.customProgramCacheKey()};return eM.vertexUv1s=d.has(1),eM.vertexUv2s=d.has(2),eM.vertexUv3s=d.has(3),d.clear(),eM},getProgramCacheKey:function(t){var i,r,a,n;let o=[];if(t.shaderID?o.push(t.shaderID):(o.push(t.customVertexShaderID),o.push(t.customFragmentShaderID)),void 0!==t.defines)for(let e in t.defines)o.push(e),o.push(t.defines[e]);return!1===t.isRawShaderMaterial&&(i=o,r=t,i.push(r.precision),i.push(r.outputColorSpace),i.push(r.envMapMode),i.push(r.envMapCubeUVHeight),i.push(r.mapUv),i.push(r.alphaMapUv),i.push(r.lightMapUv),i.push(r.aoMapUv),i.push(r.bumpMapUv),i.push(r.normalMapUv),i.push(r.displacementMapUv),i.push(r.emissiveMapUv),i.push(r.metalnessMapUv),i.push(r.roughnessMapUv),i.push(r.anisotropyMapUv),i.push(r.clearcoatMapUv),i.push(r.clearcoatNormalMapUv),i.push(r.clearcoatRoughnessMapUv),i.push(r.iridescenceMapUv),i.push(r.iridescenceThicknessMapUv),i.push(r.sheenColorMapUv),i.push(r.sheenRoughnessMapUv),i.push(r.specularMapUv),i.push(r.specularColorMapUv),i.push(r.specularIntensityMapUv),i.push(r.transmissionMapUv),i.push(r.thicknessMapUv),i.push(r.combine),i.push(r.fogExp2),i.push(r.sizeAttenuation),i.push(r.morphTargetsCount),i.push(r.morphAttributeCount),i.push(r.numSunLights),i.push(r.numDirLights),i.push(r.numPointLights),i.push(r.numSpotLights),i.push(r.numSpotLightMaps),i.push(r.numHemiLights),i.push(r.numRectAreaLights),i.push(r.numSunLightShadows),i.push(r.numDirLightShadows),i.push(r.numPointLightShadows),i.push(r.numSpotLightShadows),i.push(r.numSpotLightShadowsWithMaps),i.push(r.numLightProbes),i.push(r.shadowMapType),i.push(r.toneMapping),i.push(r.numClippingPlanes),i.push(r.numClipIntersection),i.push(r.depthPacking),a=o,n=t,l.disableAll(),n.instancing&&l.enable(0),n.instancingColor&&l.enable(1),n.instancingMorph&&l.enable(2),n.matcap&&l.enable(3),n.envMap&&l.enable(4),n.normalMapObjectSpace&&l.enable(5),n.normalMapTangentSpace&&l.enable(6),n.clearcoat&&l.enable(7),n.iridescence&&l.enable(8),n.alphaTest&&l.enable(9),n.vertexColors&&l.enable(10),n.vertexAlphas&&l.enable(11),n.vertexUv1s&&l.enable(12),n.vertexUv2s&&l.enable(13),n.vertexUv3s&&l.enable(14),n.vertexTangents&&l.enable(15),n.anisotropy&&l.enable(16),n.alphaHash&&l.enable(17),n.batching&&l.enable(18),n.dispersion&&l.enable(19),n.retroreflection&&l.enable(24),n.batchingColor&&l.enable(20),n.gradientMap&&l.enable(21),n.packedNormalMap&&l.enable(22),n.vertexNormals&&l.enable(23),a.push(l.mask),l.disableAll(),n.fog&&l.enable(0),n.useFog&&l.enable(1),n.flatShading&&l.enable(2),n.logarithmicDepthBuffer&&l.enable(3),n.reversedDepthBuffer&&l.enable(4),n.skinning&&l.enable(5),n.morphTargets&&l.enable(6),n.morphNormals&&l.enable(7),n.morphColors&&l.enable(8),n.premultipliedAlpha&&l.enable(9),n.shadowMapEnabled&&l.enable(10),n.doubleSided&&l.enable(11),n.flipSided&&l.enable(12),n.useDepthPacking&&l.enable(13),n.dithering&&l.enable(14),n.transmission&&l.enable(15),n.sheen&&l.enable(16),n.opaque&&l.enable(17),n.pointsUvs&&l.enable(18),n.decodeVideoTexture&&l.enable(19),n.decodeVideoTextureEmissive&&l.enable(20),n.alphaToCoverage&&l.enable(21),n.numLightProbeGrids>0&&l.enable(22),n.hasPositionAttribute&&l.enable(23),a.push(l.mask),o.push(e.outputColorSpace)),o.push(t.customProgramCacheKey),o.join()},getUniforms:function(e){let t,i=h[e.type];if(i){let e=s[i];t=r.LlO.clone(e.uniforms)}else t=e.uniforms;return t},acquireProgram:function(t,i){let r=f.get(i);return void 0!==r?++r.usedTimes:(r=new e6(e,i,t,n),u.push(r),f.set(i,r)),r},releaseProgram:function(e){if(0==--e.usedTimes){let t=u.indexOf(e);u[t]=u[u.length-1],u.pop(),f.delete(e.cacheKey),e.destroy()}},releaseShaderCache:function(e){c.remove(e)},programs:u,dispose:function(){c.dispose()}}}function e7(){let e=new WeakMap;return{has:function(t){return e.has(t)},get:function(t){let i=e.get(t);return void 0===i&&(i={},e.set(t,i)),i},remove:function(t){e.delete(t)},update:function(t,i,r){e.get(t)[i]=r},dispose:function(){e=new WeakMap}}}function te(e,t){if(e.groupOrder!==t.groupOrder)return e.groupOrder-t.groupOrder;if(e.renderOrder!==t.renderOrder)return e.renderOrder-t.renderOrder;if(e.material.id!==t.material.id)return e.material.id-t.material.id;if(e.materialVariant!==t.materialVariant)return e.materialVariant-t.materialVariant;if(e.z!==t.z)return e.z-t.z;else return e.id-t.id}function tt(e,t){return e.groupOrder!==t.groupOrder?e.groupOrder-t.groupOrder:e.renderOrder!==t.renderOrder?e.renderOrder-t.renderOrder:e.z!==t.z?t.z-e.z:e.id-t.id}function ti(){let e=[],t=0,i=[],r=[],a=[];function n(e){let t=0;return e.isInstancedMesh&&(t+=2),e.isSkinnedMesh&&(t+=1),t}function o(i,r,a,o,l,s){let c=e[t];return void 0===c?(c={id:i.id,object:i,geometry:r,material:a,materialVariant:n(i),groupOrder:o,renderOrder:i.renderOrder,z:l,group:s},e[t]=c):(c.id=i.id,c.object=i,c.geometry=r,c.material=a,c.materialVariant=n(i),c.groupOrder=o,c.renderOrder=i.renderOrder,c.z=l,c.group=s),t++,c}return{opaque:i,transmissive:r,transparent:a,init:function(){t=0,i.length=0,r.length=0,a.length=0},push:function(e,t,n,l,s,c,d){!0===d.reversedDepth&&(s=-s);let u=o(e,t,n,l,s,c);n.transmission>0?r.push(u):!0===n.transparent?a.push(u):i.push(u)},unshift:function(e,t,n,l,s,c){let d=o(e,t,n,l,s,c);n.transmission>0?r.unshift(d):!0===n.transparent?a.unshift(d):i.unshift(d)},finish:function(){for(let i=t,r=e.length;i<r;i++){let t=e[i];if(null===t.id)break;t.id=null,t.object=null,t.geometry=null,t.material=null,t.group=null}},sort:function(e,t){i.length>1&&i.sort(e||te),r.length>1&&r.sort(t||tt),a.length>1&&a.sort(t||tt)}}}function tr(){let e=new WeakMap;return{get:function(t,i){let r,a=e.get(t);return void 0===a?(r=new ti,e.set(t,[r])):i>=a.length?(r=new ti,a.push(r)):r=a[i],r},dispose:function(){e=new WeakMap}}}function ta(){let e={};return{get:function(t){let i;if(void 0!==e[t.id])return e[t.id];switch(t.type){case"SunLight":case"DirectionalLight":i={direction:new r.Pq0,color:new r.Q1f};break;case"SpotLight":i={position:new r.Pq0,direction:new r.Pq0,color:new r.Q1f,distance:0,coneCos:0,penumbraCos:0,decay:0};break;case"PointLight":i={position:new r.Pq0,color:new r.Q1f,distance:0,decay:0};break;case"HemisphereLight":i={direction:new r.Pq0,skyColor:new r.Q1f,groundColor:new r.Q1f};break;case"RectAreaLight":i={color:new r.Q1f,position:new r.Pq0,halfWidth:new r.Pq0,halfHeight:new r.Pq0}}return e[t.id]=i,i}}}let tn=0;function to(e,t){return 2*!!t.castShadow-2*!!e.castShadow+ +!!t.map-!!e.map}function tl(e){let t,i=new ta,a=(t={},{get:function(e){let i;if(void 0!==t[e.id])return t[e.id];switch(e.type){case"SunLight":case"DirectionalLight":case"SpotLight":i={shadowIntensity:1,shadowBias:0,shadowNormalBias:0,shadowRadius:1,shadowMapSize:new r.I9Y};break;case"PointLight":i={shadowIntensity:1,shadowBias:0,shadowNormalBias:0,shadowRadius:1,shadowMapSize:new r.I9Y,shadowCameraNear:1,shadowCameraFar:1e3}}return t[e.id]=i,i}}),n={version:0,hash:{sunLength:-1,directionalLength:-1,pointLength:-1,spotLength:-1,rectAreaLength:-1,hemiLength:-1,numSunShadows:-1,numDirectionalShadows:-1,numPointShadows:-1,numSpotShadows:-1,numSpotMaps:-1,numLightProbes:-1},ambient:[0,0,0],probe:[],sun:[],sunShadow:[],sunShadowMap:[],sunShadowMatrix:[],sunShadowCascade:[],directional:[],directionalShadow:[],directionalShadowMap:[],directionalShadowMatrix:[],spot:[],spotLightMap:[],spotShadow:[],spotShadowMap:[],spotLightMatrix:[],rectArea:[],rectAreaLTC1:null,rectAreaLTC2:null,point:[],pointShadow:[],pointShadowMap:[],pointShadowMatrix:[],hemi:[],numSpotLightShadowsWithMaps:0,numLightProbes:0};for(let e=0;e<9;e++)n.probe.push(new r.Pq0);let o=new r.Pq0,s=new r.kn4,c=new r.kn4;return{setup:function(t){let r=0,o=0,s=0;for(let e=0;e<9;e++)n.probe[e].set(0,0,0);let c=0,d=0,u=0,f=0,p=0,m=0,h=0,_=0,g=0,v=0,S=0,E=0,M=0,T=0;t.sort(to);for(let e=0,l=t.length;e<l;e++){let l=t[e],x=l.color,R=l.intensity,A=l.distance,b=null;if(l.shadow&&l.shadow.map&&(b=1030===l.shadow.map.texture.format?l.shadow.map.texture:l.shadow.map.depthTexture||l.shadow.map.texture),l.isAmbientLight)r+=x.r*R,o+=x.g*R,s+=x.b*R;else if(l.isLightProbe){for(let e=0;e<9;e++)n.probe[e].addScaledVector(l.sh.coefficients[e],R);T++}else if(l.isSunLight){let e=i.get(l);if(e.color.copy(l.color).multiplyScalar(l.intensity),l.castShadow){let e=l.shadow,t=a.get(l);t.shadowIntensity=e.intensity,t.shadowBias=e.bias,t.shadowNormalBias=e.normalBias,t.shadowRadius=e.radius,t.shadowMapSize.copy(e.mapSize).multiply(e.getFrameExtents()),n.sunShadow[d]=t,n.sunShadowMap[d]=b;let i=e.getViewportCount();for(let t=0;t<i;t++)n.sunShadowMatrix[u+t]=e.getMatrix(t),n.sunShadowCascade[u+t]=e._cascadeData[t];u+=i,d++}n.sun[c]=e,c++}else if(l.isDirectionalLight){let e=i.get(l);if(e.color.copy(l.color).multiplyScalar(l.intensity),l.castShadow){let e=l.shadow,t=a.get(l);t.shadowIntensity=e.intensity,t.shadowBias=e.bias,t.shadowNormalBias=e.normalBias,t.shadowRadius=e.radius,t.shadowMapSize=e.mapSize,n.directionalShadow[f]=t,n.directionalShadowMap[f]=b,n.directionalShadowMatrix[f]=l.shadow.matrix,g++}n.directional[f]=e,f++}else if(l.isSpotLight){let e=i.get(l);e.position.setFromMatrixPosition(l.matrixWorld),e.color.copy(x).multiplyScalar(R),e.distance=A,e.coneCos=Math.cos(l.angle),e.penumbraCos=Math.cos(l.angle*(1-l.penumbra)),e.decay=l.decay,n.spot[m]=e;let t=l.shadow;if(l.map&&(n.spotLightMap[E]=l.map,E++,t.updateMatrices(l),l.castShadow&&M++),n.spotLightMatrix[m]=t.matrix,l.castShadow){let e=a.get(l);e.shadowIntensity=t.intensity,e.shadowBias=t.bias,e.shadowNormalBias=t.normalBias,e.shadowRadius=t.radius,e.shadowMapSize=t.mapSize,n.spotShadow[m]=e,n.spotShadowMap[m]=b,S++}m++}else if(l.isRectAreaLight){let e=i.get(l);e.color.copy(x).multiplyScalar(R),e.halfWidth.set(.5*l.width,0,0),e.halfHeight.set(0,.5*l.height,0),n.rectArea[h]=e,h++}else if(l.isPointLight){let e=i.get(l);if(e.color.copy(l.color).multiplyScalar(l.intensity),e.distance=l.distance,e.decay=l.decay,l.castShadow){let e=l.shadow,t=a.get(l);t.shadowIntensity=e.intensity,t.shadowBias=e.bias,t.shadowNormalBias=e.normalBias,t.shadowRadius=e.radius,t.shadowMapSize=e.mapSize,t.shadowCameraNear=e.camera.near,t.shadowCameraFar=e.camera.far,n.pointShadow[p]=t,n.pointShadowMap[p]=b,n.pointShadowMatrix[p]=l.shadow.matrix,v++}n.point[p]=e,p++}else if(l.isHemisphereLight){let e=i.get(l);e.skyColor.copy(l.color).multiplyScalar(R),e.groundColor.copy(l.groundColor).multiplyScalar(R),n.hemi[_]=e,_++}}h>0&&(!0===e.has("OES_texture_float_linear")?(n.rectAreaLTC1=l.LTC_FLOAT_1,n.rectAreaLTC2=l.LTC_FLOAT_2):(n.rectAreaLTC1=l.LTC_HALF_1,n.rectAreaLTC2=l.LTC_HALF_2)),n.ambient[0]=r,n.ambient[1]=o,n.ambient[2]=s;let x=n.hash;(x.sunLength!==c||x.directionalLength!==f||x.pointLength!==p||x.spotLength!==m||x.rectAreaLength!==h||x.hemiLength!==_||x.numSunShadows!==d||x.numDirectionalShadows!==g||x.numPointShadows!==v||x.numSpotShadows!==S||x.numSpotMaps!==E||x.numLightProbes!==T)&&(n.sun.length=c,n.directional.length=f,n.spot.length=m,n.rectArea.length=h,n.point.length=p,n.hemi.length=_,n.sunShadow.length=d,n.sunShadowMap.length=d,n.sunShadowMatrix.length=u,n.sunShadowCascade.length=u,n.directionalShadow.length=g,n.directionalShadowMap.length=g,n.directionalShadowMatrix.length=g,n.pointShadow.length=v,n.pointShadowMap.length=v,n.pointShadowMatrix.length=v,n.spotShadow.length=S,n.spotShadowMap.length=S,n.spotLightMatrix.length=S+E-M,n.spotLightMap.length=E,n.numSpotLightShadowsWithMaps=M,n.numLightProbes=T,x.sunLength=c,x.directionalLength=f,x.pointLength=p,x.spotLength=m,x.rectAreaLength=h,x.hemiLength=_,x.numSunShadows=d,x.numDirectionalShadows=g,x.numPointShadows=v,x.numSpotShadows=S,x.numSpotMaps=E,x.numLightProbes=T,n.version=tn++)},setupView:function(e,t){let i=0,r=0,a=0,l=0,d=0,u=0,f=t.matrixWorldInverse;for(let t=0,p=e.length;t<p;t++){let p=e[t];if(p.isSunLight){let e=n.sun[i];e.direction.setFromMatrixPosition(p.matrixWorld),e.direction.transformDirection(f),i++}else if(p.isDirectionalLight){let e=n.directional[r];e.direction.setFromMatrixPosition(p.matrixWorld),o.setFromMatrixPosition(p.target.matrixWorld),e.direction.sub(o),e.direction.transformDirection(f),r++}else if(p.isSpotLight){let e=n.spot[l];e.position.setFromMatrixPosition(p.matrixWorld),e.position.applyMatrix4(f),e.direction.setFromMatrixPosition(p.matrixWorld),o.setFromMatrixPosition(p.target.matrixWorld),e.direction.sub(o),e.direction.transformDirection(f),l++}else if(p.isRectAreaLight){let e=n.rectArea[d];e.position.setFromMatrixPosition(p.matrixWorld),e.position.applyMatrix4(f),c.identity(),s.copy(p.matrixWorld),s.premultiply(f),c.extractRotation(s),e.halfWidth.set(.5*p.width,0,0),e.halfHeight.set(0,.5*p.height,0),e.halfWidth.applyMatrix4(c),e.halfHeight.applyMatrix4(c),d++}else if(p.isPointLight){let e=n.point[a];e.position.setFromMatrixPosition(p.matrixWorld),e.position.applyMatrix4(f),a++}else if(p.isHemisphereLight){let e=n.hemi[u];e.direction.setFromMatrixPosition(p.matrixWorld),e.direction.transformDirection(f),u++}}},state:n}}function ts(e){let t=new tl(e),i=[],r=[],a=[],n={lightsArray:i,shadowsArray:r,lightProbeGridArray:a,camera:null,lights:t,transmissionRenderTarget:{},textureUnits:0};return{init:function(e){n.camera=e,i.length=0,r.length=0,a.length=0},state:n,setupLights:function(){t.setup(i)},setupLightsView:function(e){t.setupView(i,e)},pushLight:function(e){i.push(e)},pushShadow:function(e){r.push(e)},pushLightProbeGrid:function(e){a.push(e)}}}function tc(e){let t=new WeakMap;return{get:function(i){let r,a=arguments.length>1&&void 0!==arguments[1]?arguments[1]:0,n=t.get(i);return void 0===n?(r=new ts(e),t.set(i,[r])):a>=n.length?(r=new ts(e),n.push(r)):r=n[a],r},dispose:function(){t=new WeakMap}}}let td=`void main() {
	gl_Position = vec4( position, 1.0 );
}`,tu=`uniform sampler2D shadow_pass;
uniform vec2 resolution;
uniform float radius;
void main() {
	const float samples = float( VSM_SAMPLES );
	float mean = 0.0;
	float squared_mean = 0.0;
	float uvStride = samples <= 1.0 ? 0.0 : 2.0 / ( samples - 1.0 );
	float uvStart = samples <= 1.0 ? 0.0 : - 1.0;
	for ( float i = 0.0; i < samples; i ++ ) {
		float uvOffset = uvStart + i * uvStride;
		#ifdef HORIZONTAL_PASS
			vec2 distribution = texture2D( shadow_pass, ( gl_FragCoord.xy + vec2( uvOffset, 0.0 ) * radius ) / resolution ).rg;
			mean += distribution.x;
			squared_mean += distribution.y * distribution.y + distribution.x * distribution.x;
		#else
			float depth = texture2D( shadow_pass, ( gl_FragCoord.xy + vec2( 0.0, uvOffset ) * radius ) / resolution ).r;
			mean += depth;
			squared_mean += depth * depth;
		#endif
	}
	mean = mean / samples;
	squared_mean = squared_mean / samples;
	float std_dev = sqrt( max( 0.0, squared_mean - mean * mean ) );
	gl_FragColor = vec4( mean, std_dev, 0.0, 1.0 );
}`,tf=[new r.Pq0(1,0,0),new r.Pq0(-1,0,0),new r.Pq0(0,1,0),new r.Pq0(0,-1,0),new r.Pq0(0,0,1),new r.Pq0(0,0,-1)],tp=[new r.Pq0(0,-1,0),new r.Pq0(0,-1,0),new r.Pq0(0,0,1),new r.Pq0(0,0,-1),new r.Pq0(0,-1,0),new r.Pq0(0,-1,0)],tm=new r.kn4,th=new r.Pq0,t_=new r.Pq0;function tg(e,t,i){let a=new r.PPD,n=new r.I9Y,o=new r.I9Y,l=new r.IUQ,s=new r.CSG,c=new r.aVO,d={},u=i.maxTextureSize,f={0:1,1:0,2:2},p=new r.BKk({defines:{VSM_SAMPLES:8},uniforms:{shadow_pass:{value:null},resolution:{value:new r.I9Y},radius:{value:4}},vertexShader:td,fragmentShader:tu}),m=p.clone();m.defines.HORIZONTAL_PASS=1;let h=new r.LoY;h.setAttribute("position",new r.THS(new Float32Array([-1,-1,.5,3,-1,.5,-1,3,.5]),3));let _=new r.eaF(h,p),g=this;this.enabled=!1,this.autoUpdate=!0,this.needsUpdate=!1,this.type=1;let v=this.type;function S(t,i,r,a){let n=null,o=!0===r.isPointLight?t.customDistanceMaterial:t.customDepthMaterial;if(void 0!==o)n=o;else if(n=!0===r.isPointLight?c:s,e.localClippingEnabled&&!0===i.clipShadows&&Array.isArray(i.clippingPlanes)&&0!==i.clippingPlanes.length||i.displacementMap&&0!==i.displacementScale||i.alphaMap&&i.alphaTest>0||i.map&&i.alphaTest>0||!0===i.alphaToCoverage){let e=n.uuid,t=i.uuid,r=d[e];void 0===r&&(r={},d[e]=r);let a=r[t];void 0===a&&(a=n.clone(),r[t]=a,i.addEventListener("dispose",E)),n=a}return n.visible=i.visible,n.wireframe=i.wireframe,3===a?n.side=null!==i.shadowSide?i.shadowSide:i.side:n.side=null!==i.shadowSide?i.shadowSide:f[i.side],n.alphaMap=i.alphaMap,n.alphaTest=!0===i.alphaToCoverage?.5:i.alphaTest,n.map=i.map,n.clipShadows=i.clipShadows,n.clippingPlanes=i.clippingPlanes,n.clipIntersection=i.clipIntersection,n.displacementMap=i.displacementMap,n.displacementScale=i.displacementScale,n.displacementBias=i.displacementBias,n.wireframeLinewidth=i.wireframeLinewidth,n.linewidth=i.linewidth,!0===r.isPointLight&&!0===n.isMeshDistanceMaterial&&(e.properties.get(n).light=r),n}function E(e){for(let t in e.target.removeEventListener("dispose",E),d){let i=d[t],r=e.target.uuid;r in i&&(i[r].dispose(),delete i[r])}}this.render=function(i,s,c){if(!1===g.enabled||!1===g.autoUpdate&&!1===g.needsUpdate||0===i.length)return;2===this.type&&((0,r.R8M)("WebGLShadowMap: PCFSoftShadowMap has been removed. Using PCFShadowMap instead."),this.type=1);let d=e.getRenderTarget(),f=e.getActiveCubeFace(),h=e.getActiveMipmapLevel(),E=e.state;E.setBlending(0),!0===E.buffers.depth.getReversed()?E.buffers.color.setClear(0,0,0,0):E.buffers.color.setClear(1,1,1,1),E.buffers.depth.setTest(!0),E.setScissorTest(!1);let M=v!==this.type;M&&s.traverse(function(e){e.material&&(Array.isArray(e.material)?e.material.forEach(e=>e.needsUpdate=!0):e.material.needsUpdate=!0)});for(let d=0,f=i.length;d<f;d++){let f=i[d],h=f.shadow;if(void 0===h){(0,r.R8M)("WebGLShadowMap:",f,"has no shadow.");continue}if(!1===h.autoUpdate&&!1===h.needsUpdate)continue;n.copy(h.mapSize);let g=h.getFrameExtents();n.multiply(g),o.copy(h.mapSize),(n.x>u||n.y>u)&&(n.x>u&&(o.x=Math.floor(u/g.x),n.x=o.x*g.x,h.mapSize.x=o.x),n.y>u&&(o.y=Math.floor(u/g.y),n.y=o.y*g.y,h.mapSize.y=o.y));let v=e.state.buffers.depth.getReversed();if(h.camera._reversedDepth=v,null===h.map||!0===M){if(null!==h.map&&(null!==h.map.depthTexture&&(h.map.depthTexture.dispose(),h.map.depthTexture=null),h.map.dispose()),3===this.type){if(f.isPointLight){(0,r.R8M)("WebGLShadowMap: VSM shadow maps are not supported for PointLights. Use PCF or BasicShadowMap instead.");continue}h.map=new r.nWS(n.x,n.y,{format:1030,type:1016,minFilter:1006,magFilter:1006,generateMipmaps:!1}),h.map.texture.name=f.name+".shadowMap",h.map.depthTexture=new r.VCu(n.x,n.y,1015),h.map.depthTexture.name=f.name+".shadowMapDepth",h.map.depthTexture.format=1026,h.map.depthTexture.compareFunction=null,h.map.depthTexture.minFilter=1003,h.map.depthTexture.magFilter=1003}else f.isPointLight?(h.map=new WebGLCubeRenderTarget(n.x),h.map.depthTexture=new r.Gc6(n.x,1014)):(h.map=new r.nWS(n.x,n.y),h.map.depthTexture=new r.VCu(n.x,n.y,1014)),h.map.depthTexture.name=f.name+".shadowMap",h.map.depthTexture.format=1026,1===this.type?(h.map.depthTexture.compareFunction=v?518:515,h.map.depthTexture.minFilter=1006,h.map.depthTexture.magFilter=1006):(h.map.depthTexture.compareFunction=null,h.map.depthTexture.minFilter=1003,h.map.depthTexture.magFilter=1003);h.camera.updateProjectionMatrix()}!0!==h.map.isWebGLCubeRenderTarget&&(h.map.width!==n.x||h.map.height!==n.y)&&h.map.setSize(n.x,n.y);let T=h.map.isWebGLCubeRenderTarget?6:h.getViewportCount();!0!==f.isPointLight&&h.updateMatrices(f,c);for(let i=0;i<T;i++){let r=h.getCamera(i);if(f.isPointLight){let e=h.camera,t=h.matrix,r=f.distance||e.far;r!==e.far&&(e.far=r,e.updateProjectionMatrix()),th.setFromMatrixPosition(f.matrixWorld),e.position.copy(th),t_.copy(e.position),t_.add(tf[i]),e.up.copy(tp[i]),e.lookAt(t_),e.updateMatrixWorld(),t.makeTranslation(-th.x,-th.y,-th.z),tm.multiplyMatrices(e.projectionMatrix,e.matrixWorldInverse),h._frustum.setFromProjectionMatrix(tm,e.coordinateSystem,e.reversedDepth)}if(h.map.isWebGLCubeRenderTarget)e.setRenderTarget(h.map,i),e.clear();else{0===i&&(e.setRenderTarget(h.map),e.clear());let t=h.getViewport(i);l.set(o.x*t.x,o.y*t.y,o.x*t.z,o.y*t.w),E.viewport(l)}a=h.getFrustum(i),function i(r,n,o,l,s){if(!1===r.visible)return;if(r.layers.test(n.layers)&&(r.isMesh||r.isLine||r.isPoints)&&(r.castShadow||r.receiveShadow&&3===s)&&(!r.frustumCulled||r.intersectsFrustum(a))){r.modelViewMatrix.multiplyMatrices(o.matrixWorldInverse,r.matrixWorld);let i=t.update(r),a=r.material;if(Array.isArray(a)){let t=i.groups;for(let c=0,d=t.length;c<d;c++){let d=t[c],u=a[d.materialIndex];if(u&&u.visible){let t=S(r,u,l,s);r.onBeforeShadow(e,r,n,o,i,t,d),e.renderBufferDirect(o,null,i,t,r,d),r.onAfterShadow(e,r,n,o,i,t,d)}}}else if(a.visible){let t=S(r,a,l,s);r.onBeforeShadow(e,r,n,o,i,t,null),e.renderBufferDirect(o,null,i,t,r,null),r.onAfterShadow(e,r,n,o,i,t,null)}}let c=r.children;for(let e=0,t=c.length;e<t;e++)i(c[e],n,o,l,s)}(s,c,r,f,this.type)}!0!==h.isPointLightShadow&&3===this.type&&function(i,a){let o=t.update(_);p.defines.VSM_SAMPLES!==i.blurSamples&&(p.defines.VSM_SAMPLES=i.blurSamples,m.defines.VSM_SAMPLES=i.blurSamples,p.needsUpdate=!0,m.needsUpdate=!0),null===i.mapPass?i.mapPass=new r.nWS(n.x,n.y,{format:1030,type:1016}):(i.mapPass.width!==i.map.width||i.mapPass.height!==i.map.height)&&i.mapPass.setSize(i.map.width,i.map.height),p.uniforms.shadow_pass.value=i.map.depthTexture,p.uniforms.resolution.value.set(i.map.width,i.map.height),p.uniforms.radius.value=i.radius,e.setRenderTarget(i.mapPass),e.clear(),e.renderBufferDirect(a,null,o,p,_,null),m.uniforms.shadow_pass.value=i.mapPass.texture,m.uniforms.resolution.value.set(i.map.width,i.map.height),m.uniforms.radius.value=i.radius,e.setRenderTarget(i.map),e.clear(),e.renderBufferDirect(a,null,o,m,_,null)}(h,c),h.needsUpdate=!1}v=this.type,g.needsUpdate=!1,e.setRenderTarget(d,f,h)}}function tv(e,t){let i=new function(){let t=!1,i=new r.IUQ,a=null,n=new r.IUQ(0,0,0,0);return{setMask:function(i){a===i||t||(e.colorMask(i,i,i,i),a=i)},setLocked:function(e){t=e},setClear:function(t,r,a,o,l){!0===l&&(t*=o,r*=o,a*=o),i.set(t,r,a,o),!1===n.equals(i)&&(e.clearColor(t,r,a,o),n.copy(i))},reset:function(){t=!1,a=null,n.set(-1,0,0,0)}}},a=new function(){let i=!1,a=!1,n=null,o=null,l=null;return{setReversed:function(e){if(a!==e){let i=t.get("EXT_clip_control");e?i.clipControlEXT(i.LOWER_LEFT_EXT,i.ZERO_TO_ONE_EXT):i.clipControlEXT(i.LOWER_LEFT_EXT,i.NEGATIVE_ONE_TO_ONE_EXT),a=e;let r=l;l=null,this.setClear(r)}},getReversed:function(){return a},setTest:function(t){t?V(e.DEPTH_TEST):W(e.DEPTH_TEST)},setMask:function(t){n===t||i||(e.depthMask(t),n=t)},setFunc:function(t){if(a&&(t=r.ri6[t]),o!==t){switch(t){case 0:e.depthFunc(e.NEVER);break;case 1:e.depthFunc(e.ALWAYS);break;case 2:e.depthFunc(e.LESS);break;case 3:default:e.depthFunc(e.LEQUAL);break;case 4:e.depthFunc(e.EQUAL);break;case 5:e.depthFunc(e.GEQUAL);break;case 6:e.depthFunc(e.GREATER);break;case 7:e.depthFunc(e.NOTEQUAL)}o=t}},setLocked:function(e){i=e},setClear:function(t){l!==t&&(l=t,a&&(t=1-t),e.clearDepth(t))},reset:function(){i=!1,n=null,o=null,l=null,a=!1}}},n=new function(){let t=!1,i=null,r=null,a=null,n=null,o=null,l=null,s=null,c=null;return{setTest:function(i){t||(i?V(e.STENCIL_TEST):W(e.STENCIL_TEST))},setMask:function(r){i===r||t||(e.stencilMask(r),i=r)},setFunc:function(t,i,o){(r!==t||a!==i||n!==o)&&(e.stencilFunc(t,i,o),r=t,a=i,n=o)},setOp:function(t,i,r){(o!==t||l!==i||s!==r)&&(e.stencilOp(t,i,r),o=t,l=i,s=r)},setLocked:function(e){t=e},setClear:function(t){c!==t&&(e.clearStencil(t),c=t)},reset:function(){t=!1,i=null,r=null,a=null,n=null,o=null,l=null,s=null,c=null}}},o=new WeakMap,l=new WeakMap,s={},c={},d={},u=new WeakMap,f=[],p=null,m=!1,h=null,_=null,g=null,v=null,S=null,E=null,M=null,T=new r.Q1f(0,0,0),x=0,R=!1,A=null,b=null,C=null,P=null,L=null,w=e.getParameter(e.MAX_COMBINED_TEXTURE_IMAGE_UNITS),U=!1,D=e.getParameter(e.VERSION);-1!==D.indexOf("WebGL")?U=parseFloat(/^WebGL (\d)/.exec(D)[1])>=1:-1!==D.indexOf("OpenGL ES")&&(U=parseFloat(/^OpenGL ES (\d)/.exec(D)[1])>=2);let I=null,N={},y=e.getParameter(e.SCISSOR_BOX),O=e.getParameter(e.VIEWPORT),F=new r.IUQ().fromArray(y),B=new r.IUQ().fromArray(O);function G(t,i,r,a){let n=new Uint8Array(4),o=e.createTexture();e.bindTexture(t,o),e.texParameteri(t,e.TEXTURE_MIN_FILTER,e.NEAREST),e.texParameteri(t,e.TEXTURE_MAG_FILTER,e.NEAREST);for(let o=0;o<r;o++)t===e.TEXTURE_3D||t===e.TEXTURE_2D_ARRAY?e.texImage3D(i,0,e.RGBA,1,1,a,0,e.RGBA,e.UNSIGNED_BYTE,n):e.texImage2D(i+o,0,e.RGBA,1,1,0,e.RGBA,e.UNSIGNED_BYTE,n);return o}let H={};function V(t){!0!==s[t]&&(e.enable(t),s[t]=!0)}function W(t){!1!==s[t]&&(e.disable(t),s[t]=!1)}H[e.TEXTURE_2D]=G(e.TEXTURE_2D,e.TEXTURE_2D,1),H[e.TEXTURE_CUBE_MAP]=G(e.TEXTURE_CUBE_MAP,e.TEXTURE_CUBE_MAP_POSITIVE_X,6),H[e.TEXTURE_2D_ARRAY]=G(e.TEXTURE_2D_ARRAY,e.TEXTURE_2D_ARRAY,1,1),H[e.TEXTURE_3D]=G(e.TEXTURE_3D,e.TEXTURE_3D,1,1),i.setClear(0,0,0,1),a.setClear(1),n.setClear(0),V(e.DEPTH_TEST),a.setFunc(3),Y(!1),q(1),V(e.CULL_FACE),X(0);let z={100:e.FUNC_ADD,101:e.FUNC_SUBTRACT,102:e.FUNC_REVERSE_SUBTRACT};z[103]=e.MIN,z[104]=e.MAX;let k={200:e.ZERO,201:e.ONE,202:e.SRC_COLOR,204:e.SRC_ALPHA,210:e.SRC_ALPHA_SATURATE,208:e.DST_COLOR,206:e.DST_ALPHA,203:e.ONE_MINUS_SRC_COLOR,205:e.ONE_MINUS_SRC_ALPHA,209:e.ONE_MINUS_DST_COLOR,207:e.ONE_MINUS_DST_ALPHA,211:e.CONSTANT_COLOR,212:e.ONE_MINUS_CONSTANT_COLOR,213:e.CONSTANT_ALPHA,214:e.ONE_MINUS_CONSTANT_ALPHA};function X(t,i,a,n,o,l,s,c,d,u){if(0===t){!0===m&&(W(e.BLEND),m=!1);return}if(!1===m&&(V(e.BLEND),m=!0),5!==t){if(t!==h||u!==R){if((100!==_||100!==S)&&(e.blendEquation(e.FUNC_ADD),_=100,S=100),u)switch(t){case 1:e.blendFuncSeparate(e.ONE,e.ONE_MINUS_SRC_ALPHA,e.ONE,e.ONE_MINUS_SRC_ALPHA);break;case 2:e.blendFunc(e.ONE,e.ONE);break;case 3:e.blendFuncSeparate(e.ZERO,e.ONE_MINUS_SRC_COLOR,e.ZERO,e.ONE);break;case 4:e.blendFuncSeparate(e.DST_COLOR,e.ONE_MINUS_SRC_ALPHA,e.ZERO,e.ONE);break;default:(0,r.z3S)("WebGLState: Invalid blending: ",t)}else switch(t){case 1:e.blendFuncSeparate(e.SRC_ALPHA,e.ONE_MINUS_SRC_ALPHA,e.ONE,e.ONE_MINUS_SRC_ALPHA);break;case 2:e.blendFuncSeparate(e.SRC_ALPHA,e.ONE,e.ONE,e.ONE);break;case 3:(0,r.z3S)("WebGLState: SubtractiveBlending requires material.premultipliedAlpha = true");break;case 4:(0,r.z3S)("WebGLState: MultiplyBlending requires material.premultipliedAlpha = true");break;default:(0,r.z3S)("WebGLState: Invalid blending: ",t)}g=null,v=null,E=null,M=null,T.set(0,0,0),x=0,h=t,R=u}return}o=o||i,l=l||a,s=s||n,(i!==_||o!==S)&&(e.blendEquationSeparate(z[i],z[o]),_=i,S=o),(a!==g||n!==v||l!==E||s!==M)&&(e.blendFuncSeparate(k[a],k[n],k[l],k[s]),g=a,v=n,E=l,M=s),(!1===c.equals(T)||d!==x)&&(e.blendColor(c.r,c.g,c.b,d),T.copy(c),x=d),h=t,R=!1}function Y(t){A!==t&&(t?e.frontFace(e.CW):e.frontFace(e.CCW),A=t)}function q(t){0!==t?(V(e.CULL_FACE),t!==b&&(1===t?e.cullFace(e.BACK):2===t?e.cullFace(e.FRONT):e.cullFace(e.FRONT_AND_BACK))):W(e.CULL_FACE),b=t}function K(t,i,r){t?(V(e.POLYGON_OFFSET_FILL),(P!==i||L!==r)&&(P=i,L=r,a.getReversed()&&(i=-i),e.polygonOffset(i,r))):W(e.POLYGON_OFFSET_FILL)}return{buffers:{color:i,depth:a,stencil:n},enable:V,disable:W,bindFramebuffer:function(t,i){return d[t]!==i&&(e.bindFramebuffer(t,i),d[t]=i,t===e.DRAW_FRAMEBUFFER&&(d[e.FRAMEBUFFER]=i),t===e.FRAMEBUFFER&&(d[e.DRAW_FRAMEBUFFER]=i),!0)},drawBuffers:function(t,i){let r=f,a=!1;if(t){void 0===(r=u.get(i))&&(r=[],u.set(i,r));let n=t.textures;if(r.length!==n.length||r[0]!==e.COLOR_ATTACHMENT0){for(let t=0,i=n.length;t<i;t++)r[t]=e.COLOR_ATTACHMENT0+t;r.length=n.length,a=!0}}else r[0]!==e.BACK&&(r[0]=e.BACK,a=!0);a&&e.drawBuffers(r)},useProgram:function(t){return p!==t&&(e.useProgram(t),p=t,!0)},setBlending:X,setMaterial:function(t,r){2===t.side?W(e.CULL_FACE):V(e.CULL_FACE);let o=1===t.side;r&&(o=!o),Y(o),1===t.blending&&!1===t.transparent?X(0):X(t.blending,t.blendEquation,t.blendSrc,t.blendDst,t.blendEquationAlpha,t.blendSrcAlpha,t.blendDstAlpha,t.blendColor,t.blendAlpha,t.premultipliedAlpha),a.setFunc(t.depthFunc),a.setTest(t.depthTest),a.setMask(t.depthWrite),i.setMask(t.colorWrite);let l=t.stencilWrite;n.setTest(l),l&&(n.setMask(t.stencilWriteMask),n.setFunc(t.stencilFunc,t.stencilRef,t.stencilFuncMask),n.setOp(t.stencilFail,t.stencilZFail,t.stencilZPass)),K(t.polygonOffset,t.polygonOffsetFactor,t.polygonOffsetUnits),!0===t.alphaToCoverage?V(e.SAMPLE_ALPHA_TO_COVERAGE):W(e.SAMPLE_ALPHA_TO_COVERAGE)},setFlipSided:Y,setCullFace:q,setLineWidth:function(t){t!==C&&(U&&e.lineWidth(t),C=t)},setPolygonOffset:K,setScissorTest:function(t){t?V(e.SCISSOR_TEST):W(e.SCISSOR_TEST)},activeTexture:function(t){void 0===t&&(t=e.TEXTURE0+w-1),I!==t&&(e.activeTexture(t),I=t)},bindTexture:function(t,i,r){void 0===r&&(r=null===I?e.TEXTURE0+w-1:I);let a=N[r];void 0===a&&(a={type:void 0,texture:void 0},N[r]=a),(a.type!==t||a.texture!==i)&&(I!==r&&(e.activeTexture(r),I=r),e.bindTexture(t,i||H[t]),a.type=t,a.texture=i)},unbindTexture:function(){let t=N[I];void 0!==t&&void 0!==t.type&&(e.bindTexture(t.type,null),t.type=void 0,t.texture=void 0)},compressedTexImage2D:function(){try{e.compressedTexImage2D(...arguments)}catch(e){(0,r.z3S)("WebGLState:",e)}},compressedTexImage3D:function(){try{e.compressedTexImage3D(...arguments)}catch(e){(0,r.z3S)("WebGLState:",e)}},texImage2D:function(){try{e.texImage2D(...arguments)}catch(e){(0,r.z3S)("WebGLState:",e)}},texImage3D:function(){try{e.texImage3D(...arguments)}catch(e){(0,r.z3S)("WebGLState:",e)}},pixelStorei:function(t,i){c[t]!==i&&(e.pixelStorei(t,i),c[t]=i)},getParameter:function(t){return void 0!==c[t]?c[t]:e.getParameter(t)},updateUBOMapping:function(t,i){let r=l.get(i);void 0===r&&(r=new WeakMap,l.set(i,r));let a=r.get(t);void 0===a&&(a=e.getUniformBlockIndex(i,t.name),r.set(t,a))},uniformBlockBinding:function(t,i){let r=l.get(i).get(t);o.get(i)!==r&&(e.uniformBlockBinding(i,r,t.__bindingPointIndex),o.set(i,r))},texStorage2D:function(){try{e.texStorage2D(...arguments)}catch(e){(0,r.z3S)("WebGLState:",e)}},texStorage3D:function(){try{e.texStorage3D(...arguments)}catch(e){(0,r.z3S)("WebGLState:",e)}},texSubImage2D:function(){try{e.texSubImage2D(...arguments)}catch(e){(0,r.z3S)("WebGLState:",e)}},texSubImage3D:function(){try{e.texSubImage3D(...arguments)}catch(e){(0,r.z3S)("WebGLState:",e)}},compressedTexSubImage2D:function(){try{e.compressedTexSubImage2D(...arguments)}catch(e){(0,r.z3S)("WebGLState:",e)}},compressedTexSubImage3D:function(){try{e.compressedTexSubImage3D(...arguments)}catch(e){(0,r.z3S)("WebGLState:",e)}},scissor:function(t){!1===F.equals(t)&&(e.scissor(t.x,t.y,t.z,t.w),F.copy(t))},viewport:function(t){!1===B.equals(t)&&(e.viewport(t.x,t.y,t.z,t.w),B.copy(t))},reset:function(){e.disable(e.BLEND),e.disable(e.CULL_FACE),e.disable(e.DEPTH_TEST),e.disable(e.POLYGON_OFFSET_FILL),e.disable(e.SCISSOR_TEST),e.disable(e.STENCIL_TEST),e.disable(e.SAMPLE_ALPHA_TO_COVERAGE),e.blendEquation(e.FUNC_ADD),e.blendFunc(e.ONE,e.ZERO),e.blendFuncSeparate(e.ONE,e.ZERO,e.ONE,e.ZERO),e.blendColor(0,0,0,0),e.colorMask(!0,!0,!0,!0),e.clearColor(0,0,0,0),e.depthMask(!0),e.depthFunc(e.LESS),a.setReversed(!1),e.clearDepth(1),e.stencilMask(0xffffffff),e.stencilFunc(e.ALWAYS,0,0xffffffff),e.stencilOp(e.KEEP,e.KEEP,e.KEEP),e.clearStencil(0),e.cullFace(e.BACK),e.frontFace(e.CCW),e.polygonOffset(0,0),e.activeTexture(e.TEXTURE0),e.bindFramebuffer(e.FRAMEBUFFER,null),e.bindFramebuffer(e.DRAW_FRAMEBUFFER,null),e.bindFramebuffer(e.READ_FRAMEBUFFER,null),e.useProgram(null),e.lineWidth(1),e.scissor(0,0,e.canvas.width,e.canvas.height),e.viewport(0,0,e.canvas.width,e.canvas.height),e.pixelStorei(e.PACK_ALIGNMENT,4),e.pixelStorei(e.UNPACK_ALIGNMENT,4),e.pixelStorei(e.UNPACK_FLIP_Y_WEBGL,!1),e.pixelStorei(e.UNPACK_PREMULTIPLY_ALPHA_WEBGL,!1),e.pixelStorei(e.UNPACK_COLORSPACE_CONVERSION_WEBGL,e.BROWSER_DEFAULT_WEBGL),e.pixelStorei(e.PACK_ROW_LENGTH,0),e.pixelStorei(e.PACK_SKIP_PIXELS,0),e.pixelStorei(e.PACK_SKIP_ROWS,0),e.pixelStorei(e.UNPACK_ROW_LENGTH,0),e.pixelStorei(e.UNPACK_IMAGE_HEIGHT,0),e.pixelStorei(e.UNPACK_SKIP_PIXELS,0),e.pixelStorei(e.UNPACK_SKIP_ROWS,0),e.pixelStorei(e.UNPACK_SKIP_IMAGES,0),s={},c={},I=null,N={},d={},u=new WeakMap,f=[],p=null,m=!1,h=null,_=null,g=null,v=null,S=null,E=null,M=null,T=new r.Q1f(0,0,0),x=0,R=!1,A=null,b=null,C=null,P=null,L=null,F.set(0,0,e.canvas.width,e.canvas.height),B.set(0,0,e.canvas.width,e.canvas.height),i.reset(),a.reset(),n.reset()}}}function tS(e,t,i,a,n,o,l){let s,c=t.has("WEBGL_multisampled_render_to_texture")?t.get("WEBGL_multisampled_render_to_texture"):null,d="u">typeof navigator&&/OculusBrowser/g.test(navigator.userAgent),u=new r.I9Y,f=new WeakMap,p=new Set,m=new WeakMap,h=!1;try{h="u">typeof OffscreenCanvas&&null!==new OffscreenCanvas(1,1).getContext("2d")}catch{}function _(e,t){return h?new OffscreenCanvas(e,t):(0,r.qq$)("canvas")}function g(e,t,i){let a=1,n=k(e);if((n.width>i||n.height>i)&&(a=i/Math.max(n.width,n.height)),a<1)if("u">typeof HTMLImageElement&&e instanceof HTMLImageElement||"u">typeof HTMLCanvasElement&&e instanceof HTMLCanvasElement||"u">typeof ImageBitmap&&e instanceof ImageBitmap||"u">typeof VideoFrame&&e instanceof VideoFrame){let i=Math.floor(a*n.width),o=Math.floor(a*n.height);void 0===s&&(s=_(i,o));let l=t?_(i,o):s;return l.width=i,l.height=o,l.getContext("2d").drawImage(e,0,0,i,o),(0,r.R8M)("WebGLRenderer: Texture has been resized from ("+n.width+"x"+n.height+") to ("+i+"x"+o+")."),l}else"data"in e&&(0,r.R8M)("WebGLRenderer: Image in DataTexture is too big ("+n.width+"x"+n.height+").");return e}function v(e){return e.generateMipmaps}function S(t){e.generateMipmap(t)}function E(i,a,n,o,l){let s,c=arguments.length>5&&void 0!==arguments[5]&&arguments[5];if(null!==i){if(void 0!==e[i])return e[i];(0,r.R8M)("WebGLRenderer: Attempt to use non-existing WebGL internal format '"+i+"'")}o&&((s=t.get("EXT_texture_norm16"))||(0,r.R8M)("WebGLRenderer: Unable to use normalized textures without EXT_texture_norm16 extension"));let d=a;if(a===e.RED&&(n===e.FLOAT&&(d=e.R32F),n===e.HALF_FLOAT&&(d=e.R16F),n===e.UNSIGNED_BYTE&&(d=e.R8),n===e.UNSIGNED_SHORT&&s&&(d=s.R16_EXT),n===e.SHORT&&s&&(d=s.R16_SNORM_EXT)),a===e.RED_INTEGER&&(n===e.UNSIGNED_BYTE&&(d=e.R8UI),n===e.UNSIGNED_SHORT&&(d=e.R16UI),n===e.UNSIGNED_INT&&(d=e.R32UI),n===e.BYTE&&(d=e.R8I),n===e.SHORT&&(d=e.R16I),n===e.INT&&(d=e.R32I)),a===e.RG&&(n===e.FLOAT&&(d=e.RG32F),n===e.HALF_FLOAT&&(d=e.RG16F),n===e.UNSIGNED_BYTE&&(d=e.RG8),n===e.UNSIGNED_SHORT&&s&&(d=s.RG16_EXT),n===e.SHORT&&s&&(d=s.RG16_SNORM_EXT)),a===e.RG_INTEGER&&(n===e.UNSIGNED_BYTE&&(d=e.RG8UI),n===e.UNSIGNED_SHORT&&(d=e.RG16UI),n===e.UNSIGNED_INT&&(d=e.RG32UI),n===e.BYTE&&(d=e.RG8I),n===e.SHORT&&(d=e.RG16I),n===e.INT&&(d=e.RG32I)),a===e.RGB_INTEGER&&(n===e.UNSIGNED_BYTE&&(d=e.RGB8UI),n===e.UNSIGNED_SHORT&&(d=e.RGB16UI),n===e.UNSIGNED_INT&&(d=e.RGB32UI),n===e.BYTE&&(d=e.RGB8I),n===e.SHORT&&(d=e.RGB16I),n===e.INT&&(d=e.RGB32I)),a===e.RGBA_INTEGER&&(n===e.UNSIGNED_BYTE&&(d=e.RGBA8UI),n===e.UNSIGNED_SHORT&&(d=e.RGBA16UI),n===e.UNSIGNED_INT&&(d=e.RGBA32UI),n===e.BYTE&&(d=e.RGBA8I),n===e.SHORT&&(d=e.RGBA16I),n===e.INT&&(d=e.RGBA32I)),a===e.RGB&&(n===e.UNSIGNED_SHORT&&s&&(d=s.RGB16_EXT),n===e.SHORT&&s&&(d=s.RGB16_SNORM_EXT),n===e.UNSIGNED_INT_5_9_9_9_REV&&(d=e.RGB9_E5),n===e.UNSIGNED_INT_10F_11F_11F_REV&&(d=e.R11F_G11F_B10F)),a===e.RGBA){let t=c?"linear":r.ppV.getTransfer(l);n===e.FLOAT&&(d=e.RGBA32F),n===e.HALF_FLOAT&&(d=e.RGBA16F),n===e.UNSIGNED_BYTE&&(d="srgb"===t?e.SRGB8_ALPHA8:e.RGBA8),n===e.UNSIGNED_SHORT&&s&&(d=s.RGBA16_EXT),n===e.SHORT&&s&&(d=s.RGBA16_SNORM_EXT),n===e.UNSIGNED_SHORT_4_4_4_4&&(d=e.RGBA4),n===e.UNSIGNED_SHORT_5_5_5_1&&(d=e.RGB5_A1)}return(d===e.R16F||d===e.R32F||d===e.RG16F||d===e.RG32F||d===e.RGBA16F||d===e.RGBA32F)&&t.get("EXT_color_buffer_float"),d}function M(t,i){let a;return t?null===i||1014===i||1020===i?a=e.DEPTH24_STENCIL8:1015===i?a=e.DEPTH32F_STENCIL8:1012===i&&(a=e.DEPTH24_STENCIL8,(0,r.R8M)("DepthTexture: 16 bit depth attachment is not supported with stencil. Using 24-bit attachment.")):null===i||1014===i||1020===i?a=e.DEPTH_COMPONENT24:1015===i?a=e.DEPTH_COMPONENT32F:1012===i&&(a=e.DEPTH_COMPONENT16),a}function T(e,t){return!0===v(e)||e.isFramebufferTexture&&1003!==e.minFilter&&1006!==e.minFilter?Math.log2(Math.max(t.width,t.height))+1:void 0!==e.mipmaps&&e.mipmaps.length>0?e.mipmaps.length:e.isCompressedTexture&&Array.isArray(e.image)?t.mipmaps.length:1}function x(e){let t=e.target;t.removeEventListener("dispose",x),function(e){let t=a.get(e);if(void 0===t.__webglInit)return;let i=e.source,r=m.get(i);if(r){let a=r[t.__cacheKey];a.usedTimes--,0===a.usedTimes&&A(e),0===Object.keys(r).length&&m.delete(i)}a.remove(e)}(t),t.isVideoTexture&&f.delete(t),t.isHTMLTexture&&p.delete(t)}function R(t){let i=t.target;i.removeEventListener("dispose",R),function(t){let i=a.get(t);if(t.depthTexture&&(t.depthTexture.dispose(),a.remove(t.depthTexture)),t.isWebGLCubeRenderTarget)for(let t=0;t<6;t++){if(Array.isArray(i.__webglFramebuffer[t]))for(let r=0;r<i.__webglFramebuffer[t].length;r++)e.deleteFramebuffer(i.__webglFramebuffer[t][r]);else e.deleteFramebuffer(i.__webglFramebuffer[t]);i.__webglDepthbuffer&&e.deleteRenderbuffer(i.__webglDepthbuffer[t])}else{if(Array.isArray(i.__webglFramebuffer))for(let t=0;t<i.__webglFramebuffer.length;t++)e.deleteFramebuffer(i.__webglFramebuffer[t]);else e.deleteFramebuffer(i.__webglFramebuffer);if(i.__webglDepthbuffer&&e.deleteRenderbuffer(i.__webglDepthbuffer),i.__webglMultisampledFramebuffer&&e.deleteFramebuffer(i.__webglMultisampledFramebuffer),i.__webglColorRenderbuffer)for(let t=0;t<i.__webglColorRenderbuffer.length;t++)i.__webglColorRenderbuffer[t]&&e.deleteRenderbuffer(i.__webglColorRenderbuffer[t]);i.__webglDepthRenderbuffer&&e.deleteRenderbuffer(i.__webglDepthRenderbuffer)}let r=t.textures;for(let t=0,i=r.length;t<i;t++){let i=a.get(r[t]);i.__webglTexture&&(e.deleteTexture(i.__webglTexture),l.memory.textures--),a.remove(r[t])}a.remove(t)}(i)}function A(t){let i=a.get(t);e.deleteTexture(i.__webglTexture);let r=t.source,n=m.get(r);delete n[i.__cacheKey],l.memory.textures--}let b=0;function C(t,n){var o;let s,c=a.get(t);if(t.isVideoTexture&&(o=t,s=l.render.frame,f.get(o)!==s&&(f.set(o,s),o.update())),!1===t.isRenderTargetTexture&&!0!==t.isExternalTexture&&t.version>0&&c.__version!==t.version){let e=t.image;if(null===e)(0,r.R8M)("WebGLRenderer: Texture marked for update but no image data found.");else{if(!1!==e.complete)return void N(c,t,n);(0,r.R8M)("WebGLRenderer: Texture marked for update but image is incomplete")}}else t.isExternalTexture&&(c.__webglTexture=t.sourceTexture?t.sourceTexture:null);i.bindTexture(e.TEXTURE_2D,c.__webglTexture,e.TEXTURE0+n)}let P={1e3:e.REPEAT,1001:e.CLAMP_TO_EDGE,1002:e.MIRRORED_REPEAT},L={1003:e.NEAREST,1004:e.NEAREST_MIPMAP_NEAREST,1005:e.NEAREST_MIPMAP_LINEAR,1006:e.LINEAR,1007:e.LINEAR_MIPMAP_NEAREST,1008:e.LINEAR_MIPMAP_LINEAR},w={512:e.NEVER,519:e.ALWAYS,513:e.LESS,515:e.LEQUAL,514:e.EQUAL,518:e.GEQUAL,516:e.GREATER,517:e.NOTEQUAL};function U(i,o){if((1015===o.type&&!1===t.has("OES_texture_float_linear")&&(1006===o.magFilter||1007===o.magFilter||1005===o.magFilter||1008===o.magFilter||1006===o.minFilter||1007===o.minFilter||1005===o.minFilter||1008===o.minFilter)&&(0,r.R8M)("WebGLRenderer: Unable to use linear filtering with floating point textures. OES_texture_float_linear not supported on this device."),e.texParameteri(i,e.TEXTURE_WRAP_S,P[o.wrapS]),e.texParameteri(i,e.TEXTURE_WRAP_T,P[o.wrapT]),(i===e.TEXTURE_3D||i===e.TEXTURE_2D_ARRAY)&&e.texParameteri(i,e.TEXTURE_WRAP_R,P[o.wrapR]),e.texParameteri(i,e.TEXTURE_MAG_FILTER,L[o.magFilter]),e.texParameteri(i,e.TEXTURE_MIN_FILTER,L[o.minFilter]),o.compareFunction&&(e.texParameteri(i,e.TEXTURE_COMPARE_MODE,e.COMPARE_REF_TO_TEXTURE),e.texParameteri(i,e.TEXTURE_COMPARE_FUNC,w[o.compareFunction])),!0===t.has("EXT_texture_filter_anisotropic"))&&1003!==o.magFilter&&(1005===o.minFilter||1008===o.minFilter)&&(1015!==o.type||!1!==t.has("OES_texture_float_linear"))&&(o.anisotropy>1||a.get(o).__currentAnisotropy)){let r=t.get("EXT_texture_filter_anisotropic");e.texParameterf(i,r.TEXTURE_MAX_ANISOTROPY_EXT,Math.min(o.anisotropy,n.getMaxAnisotropy())),a.get(o).__currentAnisotropy=o.anisotropy}}function D(t,i){let r,a=!1;void 0===t.__webglInit&&(t.__webglInit=!0,i.addEventListener("dispose",x));let n=i.source,o=m.get(n);void 0===o&&(o={},m.set(n,o));let s=((r=[]).push(i.wrapS),r.push(i.wrapT),r.push(i.wrapR||0),r.push(i.magFilter),r.push(i.minFilter),r.push(i.anisotropy),r.push(i.internalFormat),r.push(i.format),r.push(i.type),r.push(i.generateMipmaps),r.push(i.premultiplyAlpha),r.push(i.flipY),r.push(i.unpackAlignment),r.push(i.colorSpace),r.join());if(s!==t.__cacheKey){void 0===o[s]&&(o[s]={texture:e.createTexture(),usedTimes:0},l.memory.textures++,a=!0),o[s].usedTimes++;let r=o[t.__cacheKey];void 0!==r&&(o[t.__cacheKey].usedTimes--,0===r.usedTimes&&A(i)),t.__cacheKey=s,t.__webglTexture=o[s].texture}return a}function I(e,t,i){return Math.floor(Math.floor(e/i)/t)}function N(t,l,s){let c=e.TEXTURE_2D;(l.isDataArrayTexture||l.isCompressedArrayTexture)&&(c=e.TEXTURE_2D_ARRAY),l.isData3DTexture&&(c=e.TEXTURE_3D);let d=D(t,l),u=l.source;i.bindTexture(c,t.__webglTexture,e.TEXTURE0+s);let f=a.get(u);if(u.version!==f.__version||!0===d){let t;if(i.activeTexture(e.TEXTURE0+s),!1==("u">typeof ImageBitmap&&l.image instanceof ImageBitmap)){let t=r.ppV.getPrimaries(r.ppV.workingColorSpace),a=""===l.colorSpace?null:r.ppV.getPrimaries(l.colorSpace),n=""===l.colorSpace||t===a?e.NONE:e.BROWSER_DEFAULT_WEBGL;i.pixelStorei(e.UNPACK_FLIP_Y_WEBGL,l.flipY),i.pixelStorei(e.UNPACK_PREMULTIPLY_ALPHA_WEBGL,l.premultiplyAlpha),i.pixelStorei(e.UNPACK_COLORSPACE_CONVERSION_WEBGL,n)}i.pixelStorei(e.UNPACK_ALIGNMENT,l.unpackAlignment);let a=g(l.image,!1,n.maxTextureSize);a=z(l,a);let m=o.convert(l.format,l.colorSpace),h=o.convert(l.type),_=E(l.internalFormat,m,h,l.normalized,l.colorSpace,l.isVideoTexture);U(c,l);let x=l.mipmaps,R=!0!==l.isVideoTexture,A=void 0===f.__version||!0===d,b=u.dataReady,C=T(l,a);if(l.isDepthTexture)_=M(1027===l.format,l.type),A&&(R?i.texStorage2D(e.TEXTURE_2D,1,_,a.width,a.height):i.texImage2D(e.TEXTURE_2D,0,_,a.width,a.height,0,m,h,null));else if(l.isDataTexture)if(x.length>0){R&&A&&i.texStorage2D(e.TEXTURE_2D,C,_,x[0].width,x[0].height);for(let r=0,a=x.length;r<a;r++)t=x[r],R?b&&i.texSubImage2D(e.TEXTURE_2D,r,0,0,t.width,t.height,m,h,t.data):i.texImage2D(e.TEXTURE_2D,r,_,t.width,t.height,0,m,h,t.data);l.generateMipmaps=!1}else R?(A&&i.texStorage2D(e.TEXTURE_2D,C,_,a.width,a.height),b&&function(t,r,a,n){let o=t.updateRanges;if(0===o.length)i.texSubImage2D(e.TEXTURE_2D,0,0,0,r.width,r.height,a,n,r.data);else{o.sort((e,t)=>e.start-t.start);let l=0;for(let e=1;e<o.length;e++){let t=o[l],i=o[e],a=t.start+t.count,n=I(i.start,r.width,4),s=I(t.start,r.width,4);i.start<=a+1&&n===s&&I(i.start+i.count-1,r.width,4)===n?t.count=Math.max(t.count,i.start+i.count-t.start):o[++l]=i}o.length=l+1;let s=i.getParameter(e.UNPACK_ROW_LENGTH),c=i.getParameter(e.UNPACK_SKIP_PIXELS),d=i.getParameter(e.UNPACK_SKIP_ROWS);i.pixelStorei(e.UNPACK_ROW_LENGTH,r.width);for(let t=0,l=o.length;t<l;t++){let l=o[t],s=Math.floor(l.start/4),c=Math.ceil(l.count/4),d=s%r.width,u=Math.floor(s/r.width);i.pixelStorei(e.UNPACK_SKIP_PIXELS,d),i.pixelStorei(e.UNPACK_SKIP_ROWS,u),i.texSubImage2D(e.TEXTURE_2D,0,d,u,c,1,a,n,r.data)}t.clearUpdateRanges(),i.pixelStorei(e.UNPACK_ROW_LENGTH,s),i.pixelStorei(e.UNPACK_SKIP_PIXELS,c),i.pixelStorei(e.UNPACK_SKIP_ROWS,d)}}(l,a,m,h)):i.texImage2D(e.TEXTURE_2D,0,_,a.width,a.height,0,m,h,a.data);else if(l.isCompressedTexture)if(l.isCompressedArrayTexture){R&&A&&i.texStorage3D(e.TEXTURE_2D_ARRAY,C,_,x[0].width,x[0].height,a.depth);for(let n=0,o=x.length;n<o;n++)if(t=x[n],1023!==l.format)if(null!==m)if(R){if(b)if(l.layerUpdates.size>0){let a=(0,r.Nex)(t.width,t.height,l.format,l.type);for(let r of l.layerUpdates){let o=t.data.subarray(r*a/t.data.BYTES_PER_ELEMENT,(r+1)*a/t.data.BYTES_PER_ELEMENT);i.compressedTexSubImage3D(e.TEXTURE_2D_ARRAY,n,0,0,r,t.width,t.height,1,m,o)}}else i.compressedTexSubImage3D(e.TEXTURE_2D_ARRAY,n,0,0,0,t.width,t.height,a.depth,m,t.data)}else i.compressedTexImage3D(e.TEXTURE_2D_ARRAY,n,_,t.width,t.height,a.depth,0,t.data,0,0);else(0,r.R8M)("WebGLRenderer: Attempt to load unsupported compressed texture format in .uploadTexture()");else R?b&&i.texSubImage3D(e.TEXTURE_2D_ARRAY,n,0,0,0,t.width,t.height,a.depth,m,h,t.data):i.texImage3D(e.TEXTURE_2D_ARRAY,n,_,t.width,t.height,a.depth,0,m,h,t.data);l.layerUpdates.size>0&&l.clearLayerUpdates()}else{R&&A&&i.texStorage2D(e.TEXTURE_2D,C,_,x[0].width,x[0].height);for(let a=0,n=x.length;a<n;a++)t=x[a],1023!==l.format?null!==m?R?b&&i.compressedTexSubImage2D(e.TEXTURE_2D,a,0,0,t.width,t.height,m,t.data):i.compressedTexImage2D(e.TEXTURE_2D,a,_,t.width,t.height,0,t.data):(0,r.R8M)("WebGLRenderer: Attempt to load unsupported compressed texture format in .uploadTexture()"):R?b&&i.texSubImage2D(e.TEXTURE_2D,a,0,0,t.width,t.height,m,h,t.data):i.texImage2D(e.TEXTURE_2D,a,_,t.width,t.height,0,m,h,t.data)}else if(l.isDataArrayTexture)if(R){if(A&&i.texStorage3D(e.TEXTURE_2D_ARRAY,C,_,a.width,a.height,a.depth),b)if(l.layerUpdates.size>0){let t=(0,r.Nex)(a.width,a.height,l.format,l.type);for(let r of l.layerUpdates){let n=a.data.subarray(r*t/a.data.BYTES_PER_ELEMENT,(r+1)*t/a.data.BYTES_PER_ELEMENT);i.texSubImage3D(e.TEXTURE_2D_ARRAY,0,0,0,r,a.width,a.height,1,m,h,n)}l.clearLayerUpdates()}else i.texSubImage3D(e.TEXTURE_2D_ARRAY,0,0,0,0,a.width,a.height,a.depth,m,h,a.data)}else i.texImage3D(e.TEXTURE_2D_ARRAY,0,_,a.width,a.height,a.depth,0,m,h,a.data);else if(l.isData3DTexture)R?(A&&i.texStorage3D(e.TEXTURE_3D,C,_,a.width,a.height,a.depth),b&&i.texSubImage3D(e.TEXTURE_3D,0,0,0,0,a.width,a.height,a.depth,m,h,a.data)):i.texImage3D(e.TEXTURE_3D,0,_,a.width,a.height,a.depth,0,m,h,a.data);else if(l.isFramebufferTexture){if(A)if(R)i.texStorage2D(e.TEXTURE_2D,C,_,a.width,a.height);else{let t=a.width,r=a.height;for(let a=0;a<C;a++)i.texImage2D(e.TEXTURE_2D,a,_,t,r,0,m,h,null),t>>=1,r>>=1}}else if(l.isHTMLTexture){if("texElementImage2D"in e){let t=e.canvas;if(t.hasAttribute("layoutsubtree")||t.setAttribute("layoutsubtree","true"),a.parentNode!==t){t.appendChild(a),p.add(l),t.onpaint=e=>{let t=e.changedElements;for(let e of p)t.includes(e.image)&&(e.needsUpdate=!0)},t.requestPaint();return}if(3===e.texElementImage2D.length)e.texElementImage2D(e.TEXTURE_2D,e.RGBA8,a);else{let t=e.RGBA,i=e.RGBA,r=e.UNSIGNED_BYTE;e.texElementImage2D(e.TEXTURE_2D,0,t,i,r,a)}e.texParameteri(e.TEXTURE_2D,e.TEXTURE_MIN_FILTER,e.LINEAR),e.texParameteri(e.TEXTURE_2D,e.TEXTURE_WRAP_S,e.CLAMP_TO_EDGE),e.texParameteri(e.TEXTURE_2D,e.TEXTURE_WRAP_T,e.CLAMP_TO_EDGE)}}else if(x.length>0){if(R&&A){let t=k(x[0]);i.texStorage2D(e.TEXTURE_2D,C,_,t.width,t.height)}for(let r=0,a=x.length;r<a;r++)t=x[r],R?b&&i.texSubImage2D(e.TEXTURE_2D,r,0,0,m,h,t):i.texImage2D(e.TEXTURE_2D,r,_,m,h,t);l.generateMipmaps=!1}else if(R){if(A){let t=k(a);i.texStorage2D(e.TEXTURE_2D,C,_,t.width,t.height)}b&&i.texSubImage2D(e.TEXTURE_2D,0,0,0,m,h,a)}else i.texImage2D(e.TEXTURE_2D,0,_,m,h,a);v(l)&&S(c),f.__version=u.version,l.onUpdate&&l.onUpdate(l)}t.__version=l.version}function y(t,r,n,l,s,d){let u=o.convert(n.format,n.colorSpace),f=o.convert(n.type),p=E(n.internalFormat,u,f,n.normalized,n.colorSpace),m=a.get(r),h=a.get(n);if(h.__renderTarget=r,!m.__hasExternalTextures){let t=Math.max(1,r.width>>d),a=Math.max(1,r.height>>d);s===e.TEXTURE_3D||s===e.TEXTURE_2D_ARRAY?i.texImage3D(s,d,p,t,a,r.depth,0,u,f,null):i.texImage2D(s,d,p,t,a,0,u,f,null)}i.bindFramebuffer(e.FRAMEBUFFER,t),W(r)?c.framebufferTexture2DMultisampleEXT(e.FRAMEBUFFER,l,s,h.__webglTexture,0,V(r)):(s===e.TEXTURE_2D||s>=e.TEXTURE_CUBE_MAP_POSITIVE_X&&s<=e.TEXTURE_CUBE_MAP_NEGATIVE_Z)&&e.framebufferTexture2D(e.FRAMEBUFFER,l,s,h.__webglTexture,d),i.bindFramebuffer(e.FRAMEBUFFER,null)}function O(t,i,r){if(e.bindRenderbuffer(e.RENDERBUFFER,t),i.depthBuffer){let a=i.depthTexture,n=a&&a.isDepthTexture?a.type:null,o=M(i.stencilBuffer,n),l=i.stencilBuffer?e.DEPTH_STENCIL_ATTACHMENT:e.DEPTH_ATTACHMENT;W(i)?c.renderbufferStorageMultisampleEXT(e.RENDERBUFFER,V(i),o,i.width,i.height):r?e.renderbufferStorageMultisample(e.RENDERBUFFER,V(i),o,i.width,i.height):e.renderbufferStorage(e.RENDERBUFFER,o,i.width,i.height),e.framebufferRenderbuffer(e.FRAMEBUFFER,l,e.RENDERBUFFER,t)}else{let t=i.textures;for(let a=0;a<t.length;a++){let n=t[a],l=o.convert(n.format,n.colorSpace),s=o.convert(n.type),d=E(n.internalFormat,l,s,n.normalized,n.colorSpace);W(i)?c.renderbufferStorageMultisampleEXT(e.RENDERBUFFER,V(i),d,i.width,i.height):r?e.renderbufferStorageMultisample(e.RENDERBUFFER,V(i),d,i.width,i.height):e.renderbufferStorage(e.RENDERBUFFER,d,i.width,i.height)}}e.bindRenderbuffer(e.RENDERBUFFER,null)}function F(t,r,n){let l=!0===r.isWebGLCubeRenderTarget;if(i.bindFramebuffer(e.FRAMEBUFFER,t),!(r.depthTexture&&r.depthTexture.isDepthTexture))throw Error("THREE.WebGLTextures: renderTarget.depthTexture must be an instance of THREE.DepthTexture.");let s=a.get(r.depthTexture);if(s.__renderTarget=r,s.__webglTexture&&r.depthTexture.image.width===r.width&&r.depthTexture.image.height===r.height||(r.depthTexture.image.width=r.width,r.depthTexture.image.height=r.height,r.depthTexture.needsUpdate=!0),l){if(void 0===s.__webglInit&&(s.__webglInit=!0,r.depthTexture.addEventListener("dispose",x)),void 0===s.__webglTexture){let t;s.__webglTexture=e.createTexture(),i.bindTexture(e.TEXTURE_CUBE_MAP,s.__webglTexture),U(e.TEXTURE_CUBE_MAP,r.depthTexture);let a=o.convert(r.depthTexture.format),n=o.convert(r.depthTexture.type);1026===r.depthTexture.format?t=e.DEPTH_COMPONENT24:1027===r.depthTexture.format&&(t=e.DEPTH24_STENCIL8);for(let i=0;i<6;i++)e.texImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+i,0,t,r.width,r.height,0,a,n,null)}}else C(r.depthTexture,0);let d=s.__webglTexture,u=V(r),f=l?e.TEXTURE_CUBE_MAP_POSITIVE_X+n:e.TEXTURE_2D,p=1027===r.depthTexture.format?e.DEPTH_STENCIL_ATTACHMENT:e.DEPTH_ATTACHMENT;if(1026===r.depthTexture.format)W(r)?c.framebufferTexture2DMultisampleEXT(e.FRAMEBUFFER,p,f,d,0,u):e.framebufferTexture2D(e.FRAMEBUFFER,p,f,d,0);else if(1027===r.depthTexture.format)W(r)?c.framebufferTexture2DMultisampleEXT(e.FRAMEBUFFER,p,f,d,0,u):e.framebufferTexture2D(e.FRAMEBUFFER,p,f,d,0);else throw Error("THREE.WebGLTextures: Unknown depthTexture format.")}function B(t){let r=a.get(t),n=!0===t.isWebGLCubeRenderTarget;if(r.__boundDepthTexture!==t.depthTexture){let e=t.depthTexture;if(r.__depthDisposeCallback&&r.__depthDisposeCallback(),e){let t=()=>{delete r.__boundDepthTexture,delete r.__depthDisposeCallback,e.removeEventListener("dispose",t)};e.addEventListener("dispose",t),r.__depthDisposeCallback=t}r.__boundDepthTexture=e}if(t.depthTexture&&!r.__autoAllocateDepthBuffer)if(n)for(let e=0;e<6;e++)F(r.__webglFramebuffer[e],t,e);else{let e=t.texture.mipmaps;e&&e.length>0?F(r.__webglFramebuffer[0],t,0):F(r.__webglFramebuffer,t,0)}else if(n){r.__webglDepthbuffer=[];for(let a=0;a<6;a++)if(i.bindFramebuffer(e.FRAMEBUFFER,r.__webglFramebuffer[a]),void 0===r.__webglDepthbuffer[a])r.__webglDepthbuffer[a]=e.createRenderbuffer(),O(r.__webglDepthbuffer[a],t,!1);else{let i=t.stencilBuffer?e.DEPTH_STENCIL_ATTACHMENT:e.DEPTH_ATTACHMENT,n=r.__webglDepthbuffer[a];e.bindRenderbuffer(e.RENDERBUFFER,n),e.framebufferRenderbuffer(e.FRAMEBUFFER,i,e.RENDERBUFFER,n)}}else{let a=t.texture.mipmaps;if(a&&a.length>0?i.bindFramebuffer(e.FRAMEBUFFER,r.__webglFramebuffer[0]):i.bindFramebuffer(e.FRAMEBUFFER,r.__webglFramebuffer),void 0===r.__webglDepthbuffer)r.__webglDepthbuffer=e.createRenderbuffer(),O(r.__webglDepthbuffer,t,!1);else{let i=t.stencilBuffer?e.DEPTH_STENCIL_ATTACHMENT:e.DEPTH_ATTACHMENT,a=r.__webglDepthbuffer;e.bindRenderbuffer(e.RENDERBUFFER,a),e.framebufferRenderbuffer(e.FRAMEBUFFER,i,e.RENDERBUFFER,a)}}i.bindFramebuffer(e.FRAMEBUFFER,null)}let G=[],H=[];function V(e){return Math.min(n.maxSamples,e.samples)}function W(e){let i=a.get(e);return e.samples>0&&!0===t.has("WEBGL_multisampled_render_to_texture")&&!1!==i.__useRenderToTexture}function z(e,t){let i=e.colorSpace,a=e.format,n=e.type;return!0===e.isCompressedTexture||!0===e.isVideoTexture||i!==r.Zr2&&""!==i&&("srgb"===r.ppV.getTransfer(i)?(1023!==a||1009!==n)&&(0,r.R8M)("WebGLTextures: sRGB encoded textures have to use RGBAFormat and UnsignedByteType."):(0,r.z3S)("WebGLTextures: Unsupported texture color space:",i)),t}function k(e){return"u">typeof HTMLImageElement&&e instanceof HTMLImageElement?(u.width=e.naturalWidth||e.width,u.height=e.naturalHeight||e.height):"u">typeof VideoFrame&&e instanceof VideoFrame?(u.width=e.displayWidth,u.height=e.displayHeight):(u.width=e.width,u.height=e.height),u}this.allocateTextureUnit=function(){let e=b;return e>=n.maxTextures&&(0,r.R8M)("WebGLTextures: Trying to use "+(e+1)+" texture units while this GPU supports only "+n.maxTextures),b+=1,e},this.resetTextureUnits=function(){b=0},this.getTextureUnits=function(){return b},this.setTextureUnits=function(e){b=e},this.setTexture2D=C,this.setTexture2DArray=function(t,r){let n=a.get(t);!1===t.isRenderTargetTexture&&t.version>0&&n.__version!==t.version?N(n,t,r):(t.isExternalTexture&&(n.__webglTexture=t.sourceTexture?t.sourceTexture:null),i.bindTexture(e.TEXTURE_2D_ARRAY,n.__webglTexture,e.TEXTURE0+r))},this.setTexture3D=function(t,r){let n=a.get(t);!1===t.isRenderTargetTexture&&t.version>0&&n.__version!==t.version?N(n,t,r):i.bindTexture(e.TEXTURE_3D,n.__webglTexture,e.TEXTURE0+r)},this.setTextureCube=function(t,l){let s=a.get(t);!0!==t.isCubeDepthTexture&&t.version>0&&s.__version!==t.version?function(t,l,s){if(6!==l.image.length)return;let c=D(t,l),d=l.source;i.bindTexture(e.TEXTURE_CUBE_MAP,t.__webglTexture,e.TEXTURE0+s);let u=a.get(d);if(d.version!==u.__version||!0===c){let t;i.activeTexture(e.TEXTURE0+s);let a=r.ppV.getPrimaries(r.ppV.workingColorSpace),f=""===l.colorSpace?null:r.ppV.getPrimaries(l.colorSpace),p=""===l.colorSpace||a===f?e.NONE:e.BROWSER_DEFAULT_WEBGL;i.pixelStorei(e.UNPACK_FLIP_Y_WEBGL,l.flipY),i.pixelStorei(e.UNPACK_PREMULTIPLY_ALPHA_WEBGL,l.premultiplyAlpha),i.pixelStorei(e.UNPACK_ALIGNMENT,l.unpackAlignment),i.pixelStorei(e.UNPACK_COLORSPACE_CONVERSION_WEBGL,p);let m=l.isCompressedTexture||l.image[0].isCompressedTexture,h=l.image[0]&&l.image[0].isDataTexture,_=[];for(let e=0;e<6;e++)m||h?_[e]=h?l.image[e].image:l.image[e]:_[e]=g(l.image[e],!0,n.maxCubemapSize),_[e]=z(l,_[e]);let M=_[0],x=o.convert(l.format,l.colorSpace),R=o.convert(l.type),A=E(l.internalFormat,x,R,l.normalized,l.colorSpace),b=!0!==l.isVideoTexture,C=void 0===u.__version||!0===c,P=d.dataReady,L=T(l,M);if(U(e.TEXTURE_CUBE_MAP,l),m){b&&C&&i.texStorage2D(e.TEXTURE_CUBE_MAP,L,A,M.width,M.height);for(let a=0;a<6;a++){t=_[a].mipmaps;for(let n=0;n<t.length;n++){let o=t[n];1023!==l.format?null!==x?b?P&&i.compressedTexSubImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+a,n,0,0,o.width,o.height,x,o.data):i.compressedTexImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+a,n,A,o.width,o.height,0,o.data):(0,r.R8M)("WebGLRenderer: Attempt to load unsupported compressed texture format in .setTextureCube()"):b?P&&i.texSubImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+a,n,0,0,o.width,o.height,x,R,o.data):i.texImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+a,n,A,o.width,o.height,0,x,R,o.data)}}}else{if(t=l.mipmaps,b&&C){t.length>0&&L++;let r=k(_[0]);i.texStorage2D(e.TEXTURE_CUBE_MAP,L,A,r.width,r.height)}for(let r=0;r<6;r++)if(h){b?P&&i.texSubImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+r,0,0,0,_[r].width,_[r].height,x,R,_[r].data):i.texImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+r,0,A,_[r].width,_[r].height,0,x,R,_[r].data);for(let a=0;a<t.length;a++){let n=t[a].image[r].image;b?P&&i.texSubImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+r,a+1,0,0,n.width,n.height,x,R,n.data):i.texImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+r,a+1,A,n.width,n.height,0,x,R,n.data)}}else{b?P&&i.texSubImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+r,0,0,0,x,R,_[r]):i.texImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+r,0,A,x,R,_[r]);for(let a=0;a<t.length;a++){let n=t[a];b?P&&i.texSubImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+r,a+1,0,0,x,R,n.image[r]):i.texImage2D(e.TEXTURE_CUBE_MAP_POSITIVE_X+r,a+1,A,x,R,n.image[r])}}}v(l)&&S(e.TEXTURE_CUBE_MAP),u.__version=d.version,l.onUpdate&&l.onUpdate(l)}t.__version=l.version}(s,t,l):i.bindTexture(e.TEXTURE_CUBE_MAP,s.__webglTexture,e.TEXTURE0+l)},this.rebindTextures=function(t,i,r){let n=a.get(t);void 0!==i&&y(n.__webglFramebuffer,t,t.texture,e.COLOR_ATTACHMENT0,e.TEXTURE_2D,0),void 0!==r&&B(t)},this.setupRenderTarget=function(t){let r=t.texture,n=a.get(t),s=a.get(r);t.addEventListener("dispose",R);let c=t.textures,d=!0===t.isWebGLCubeRenderTarget,u=c.length>1;if(!u&&(void 0===s.__webglTexture&&(s.__webglTexture=e.createTexture()),s.__version=r.version,l.memory.textures++),d){n.__webglFramebuffer=[];for(let t=0;t<6;t++)if(r.mipmaps&&r.mipmaps.length>0){n.__webglFramebuffer[t]=[];for(let i=0;i<r.mipmaps.length;i++)n.__webglFramebuffer[t][i]=e.createFramebuffer()}else n.__webglFramebuffer[t]=e.createFramebuffer()}else{if(r.mipmaps&&r.mipmaps.length>0){n.__webglFramebuffer=[];for(let t=0;t<r.mipmaps.length;t++)n.__webglFramebuffer[t]=e.createFramebuffer()}else n.__webglFramebuffer=e.createFramebuffer();if(u)for(let t=0,i=c.length;t<i;t++){let i=a.get(c[t]);void 0===i.__webglTexture&&(i.__webglTexture=e.createTexture(),l.memory.textures++)}if(t.samples>0&&!1===W(t)){n.__webglMultisampledFramebuffer=e.createFramebuffer(),n.__webglColorRenderbuffer=[],i.bindFramebuffer(e.FRAMEBUFFER,n.__webglMultisampledFramebuffer);for(let i=0;i<c.length;i++){let r=c[i];n.__webglColorRenderbuffer[i]=e.createRenderbuffer(),e.bindRenderbuffer(e.RENDERBUFFER,n.__webglColorRenderbuffer[i]);let a=o.convert(r.format,r.colorSpace),l=o.convert(r.type),s=E(r.internalFormat,a,l,r.normalized,r.colorSpace,!0===t.isXRRenderTarget),d=V(t);e.renderbufferStorageMultisample(e.RENDERBUFFER,d,s,t.width,t.height),e.framebufferRenderbuffer(e.FRAMEBUFFER,e.COLOR_ATTACHMENT0+i,e.RENDERBUFFER,n.__webglColorRenderbuffer[i])}e.bindRenderbuffer(e.RENDERBUFFER,null),t.depthBuffer&&(n.__webglDepthRenderbuffer=e.createRenderbuffer(),O(n.__webglDepthRenderbuffer,t,!0)),i.bindFramebuffer(e.FRAMEBUFFER,null)}}if(d){i.bindTexture(e.TEXTURE_CUBE_MAP,s.__webglTexture),U(e.TEXTURE_CUBE_MAP,r);for(let i=0;i<6;i++)if(r.mipmaps&&r.mipmaps.length>0)for(let a=0;a<r.mipmaps.length;a++)y(n.__webglFramebuffer[i][a],t,r,e.COLOR_ATTACHMENT0,e.TEXTURE_CUBE_MAP_POSITIVE_X+i,a);else y(n.__webglFramebuffer[i],t,r,e.COLOR_ATTACHMENT0,e.TEXTURE_CUBE_MAP_POSITIVE_X+i,0);v(r)&&S(e.TEXTURE_CUBE_MAP),i.unbindTexture()}else if(u){for(let r=0,o=c.length;r<o;r++){let o=c[r],l=a.get(o),s=e.TEXTURE_2D;(t.isWebGL3DRenderTarget||t.isWebGLArrayRenderTarget)&&(s=t.isWebGL3DRenderTarget?e.TEXTURE_3D:e.TEXTURE_2D_ARRAY),i.bindTexture(s,l.__webglTexture),U(s,o),y(n.__webglFramebuffer,t,o,e.COLOR_ATTACHMENT0+r,s,0),v(o)&&S(s)}i.unbindTexture()}else{let a=e.TEXTURE_2D;if((t.isWebGL3DRenderTarget||t.isWebGLArrayRenderTarget)&&(a=t.isWebGL3DRenderTarget?e.TEXTURE_3D:e.TEXTURE_2D_ARRAY),i.bindTexture(a,s.__webglTexture),U(a,r),r.mipmaps&&r.mipmaps.length>0)for(let i=0;i<r.mipmaps.length;i++)y(n.__webglFramebuffer[i],t,r,e.COLOR_ATTACHMENT0,a,i);else y(n.__webglFramebuffer,t,r,e.COLOR_ATTACHMENT0,a,0);v(r)&&S(a),i.unbindTexture()}t.depthBuffer&&B(t)},this.updateRenderTargetMipmap=function(t){let r=t.textures;for(let n=0,o=r.length;n<o;n++){let o=r[n];if(v(o)){let r=t.isWebGLCubeRenderTarget?e.TEXTURE_CUBE_MAP:t.isWebGL3DRenderTarget?e.TEXTURE_3D:t.isWebGLArrayRenderTarget||t.isCompressedArrayTexture?e.TEXTURE_2D_ARRAY:e.TEXTURE_2D,n=a.get(o).__webglTexture;i.bindTexture(r,n),S(r),i.unbindTexture()}}},this.updateMultisampleRenderTarget=function(t){if(t.samples>0){if(!1===W(t)){let r=t.textures,n=t.width,o=t.height,l=e.COLOR_BUFFER_BIT,s=t.stencilBuffer?e.DEPTH_STENCIL_ATTACHMENT:e.DEPTH_ATTACHMENT,c=a.get(t),u=r.length>1;if(u)for(let t=0;t<r.length;t++)i.bindFramebuffer(e.FRAMEBUFFER,c.__webglMultisampledFramebuffer),e.framebufferRenderbuffer(e.FRAMEBUFFER,e.COLOR_ATTACHMENT0+t,e.RENDERBUFFER,null),i.bindFramebuffer(e.FRAMEBUFFER,c.__webglFramebuffer),e.framebufferTexture2D(e.DRAW_FRAMEBUFFER,e.COLOR_ATTACHMENT0+t,e.TEXTURE_2D,null,0);i.bindFramebuffer(e.READ_FRAMEBUFFER,c.__webglMultisampledFramebuffer);let f=t.texture.mipmaps;f&&f.length>0?i.bindFramebuffer(e.DRAW_FRAMEBUFFER,c.__webglFramebuffer[0]):i.bindFramebuffer(e.DRAW_FRAMEBUFFER,c.__webglFramebuffer);for(let i=0;i<r.length;i++){if(t.resolveDepthBuffer&&(t.depthBuffer&&(l|=e.DEPTH_BUFFER_BIT),t.stencilBuffer&&t.resolveStencilBuffer&&(l|=e.STENCIL_BUFFER_BIT)),u){e.framebufferRenderbuffer(e.READ_FRAMEBUFFER,e.COLOR_ATTACHMENT0,e.RENDERBUFFER,c.__webglColorRenderbuffer[i]);let t=a.get(r[i]).__webglTexture;e.framebufferTexture2D(e.DRAW_FRAMEBUFFER,e.COLOR_ATTACHMENT0,e.TEXTURE_2D,t,0)}e.blitFramebuffer(0,0,n,o,0,0,n,o,l,e.NEAREST),!0===d&&(G.length=0,H.length=0,G.push(e.COLOR_ATTACHMENT0+i),t.depthBuffer&&!1===t.storeMultisampledDepthBuffer&&(G.push(s),H.push(s),e.invalidateFramebuffer(e.DRAW_FRAMEBUFFER,H)),e.invalidateFramebuffer(e.READ_FRAMEBUFFER,G))}if(i.bindFramebuffer(e.READ_FRAMEBUFFER,null),i.bindFramebuffer(e.DRAW_FRAMEBUFFER,null),u)for(let t=0;t<r.length;t++){i.bindFramebuffer(e.FRAMEBUFFER,c.__webglMultisampledFramebuffer),e.framebufferRenderbuffer(e.FRAMEBUFFER,e.COLOR_ATTACHMENT0+t,e.RENDERBUFFER,c.__webglColorRenderbuffer[t]);let n=a.get(r[t]).__webglTexture;i.bindFramebuffer(e.FRAMEBUFFER,c.__webglFramebuffer),e.framebufferTexture2D(e.DRAW_FRAMEBUFFER,e.COLOR_ATTACHMENT0+t,e.TEXTURE_2D,n,0)}i.bindFramebuffer(e.DRAW_FRAMEBUFFER,c.__webglMultisampledFramebuffer)}else if(t.depthBuffer&&!1===t.storeMultisampledDepthBuffer&&d){let i=t.stencilBuffer?e.DEPTH_STENCIL_ATTACHMENT:e.DEPTH_ATTACHMENT;e.invalidateFramebuffer(e.DRAW_FRAMEBUFFER,[i])}}},this.setupDepthRenderbuffer=B,this.setupFrameBufferTexture=y,this.useMultisampledRTT=W,this.isReversedDepthBuffer=function(){return i.buffers.depth.getReversed()}}function tE(e,t){return{convert:function(i){let a,n=arguments.length>1&&void 0!==arguments[1]?arguments[1]:"",o=r.ppV.getTransfer(n);if(1009===i)return e.UNSIGNED_BYTE;if(1017===i)return e.UNSIGNED_SHORT_4_4_4_4;if(1018===i)return e.UNSIGNED_SHORT_5_5_5_1;if(35902===i)return e.UNSIGNED_INT_5_9_9_9_REV;if(35899===i)return e.UNSIGNED_INT_10F_11F_11F_REV;if(1010===i)return e.BYTE;if(1011===i)return e.SHORT;if(1012===i)return e.UNSIGNED_SHORT;if(1013===i)return e.INT;if(1014===i)return e.UNSIGNED_INT;if(1015===i)return e.FLOAT;if(1016===i)return e.HALF_FLOAT;if(1021===i)return e.ALPHA;if(1022===i)return e.RGB;if(1023===i)return e.RGBA;if(1026===i)return e.DEPTH_COMPONENT;if(1027===i)return e.DEPTH_STENCIL;if(1028===i)return e.RED;if(1029===i)return e.RED_INTEGER;if(1030===i)return e.RG;if(1031===i)return e.RG_INTEGER;if(1033===i)return e.RGBA_INTEGER;if(33776===i||33777===i||33778===i||33779===i)if("srgb"===o){if(null===(a=t.get("WEBGL_compressed_texture_s3tc_srgb")))return null;if(33776===i)return a.COMPRESSED_SRGB_S3TC_DXT1_EXT;if(33777===i)return a.COMPRESSED_SRGB_ALPHA_S3TC_DXT1_EXT;if(33778===i)return a.COMPRESSED_SRGB_ALPHA_S3TC_DXT3_EXT;if(33779===i)return a.COMPRESSED_SRGB_ALPHA_S3TC_DXT5_EXT}else{if(null===(a=t.get("WEBGL_compressed_texture_s3tc")))return null;if(33776===i)return a.COMPRESSED_RGB_S3TC_DXT1_EXT;if(33777===i)return a.COMPRESSED_RGBA_S3TC_DXT1_EXT;if(33778===i)return a.COMPRESSED_RGBA_S3TC_DXT3_EXT;if(33779===i)return a.COMPRESSED_RGBA_S3TC_DXT5_EXT}if(35840===i||35841===i||35842===i||35843===i){if(null===(a=t.get("WEBGL_compressed_texture_pvrtc")))return null;if(35840===i)return a.COMPRESSED_RGB_PVRTC_4BPPV1_IMG;if(35841===i)return a.COMPRESSED_RGB_PVRTC_2BPPV1_IMG;if(35842===i)return a.COMPRESSED_RGBA_PVRTC_4BPPV1_IMG;if(35843===i)return a.COMPRESSED_RGBA_PVRTC_2BPPV1_IMG}if(36196===i||37492===i||37496===i||37488===i||37489===i||37490===i||37491===i){if(null===(a=t.get("WEBGL_compressed_texture_etc")))return null;if(36196===i||37492===i)return"srgb"===o?a.COMPRESSED_SRGB8_ETC2:a.COMPRESSED_RGB8_ETC2;if(37496===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ETC2_EAC:a.COMPRESSED_RGBA8_ETC2_EAC;if(37488===i)return a.COMPRESSED_R11_EAC;if(37489===i)return a.COMPRESSED_SIGNED_R11_EAC;if(37490===i)return a.COMPRESSED_RG11_EAC;if(37491===i)return a.COMPRESSED_SIGNED_RG11_EAC}if(37808===i||37809===i||37810===i||37811===i||37812===i||37813===i||37814===i||37815===i||37816===i||37817===i||37818===i||37819===i||37820===i||37821===i){if(null===(a=t.get("WEBGL_compressed_texture_astc")))return null;if(37808===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_4x4_KHR:a.COMPRESSED_RGBA_ASTC_4x4_KHR;if(37809===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_5x4_KHR:a.COMPRESSED_RGBA_ASTC_5x4_KHR;if(37810===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_5x5_KHR:a.COMPRESSED_RGBA_ASTC_5x5_KHR;if(37811===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_6x5_KHR:a.COMPRESSED_RGBA_ASTC_6x5_KHR;if(37812===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_6x6_KHR:a.COMPRESSED_RGBA_ASTC_6x6_KHR;if(37813===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_8x5_KHR:a.COMPRESSED_RGBA_ASTC_8x5_KHR;if(37814===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_8x6_KHR:a.COMPRESSED_RGBA_ASTC_8x6_KHR;if(37815===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_8x8_KHR:a.COMPRESSED_RGBA_ASTC_8x8_KHR;if(37816===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_10x5_KHR:a.COMPRESSED_RGBA_ASTC_10x5_KHR;if(37817===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_10x6_KHR:a.COMPRESSED_RGBA_ASTC_10x6_KHR;if(37818===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_10x8_KHR:a.COMPRESSED_RGBA_ASTC_10x8_KHR;if(37819===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_10x10_KHR:a.COMPRESSED_RGBA_ASTC_10x10_KHR;if(37820===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_12x10_KHR:a.COMPRESSED_RGBA_ASTC_12x10_KHR;if(37821===i)return"srgb"===o?a.COMPRESSED_SRGB8_ALPHA8_ASTC_12x12_KHR:a.COMPRESSED_RGBA_ASTC_12x12_KHR}if(36492===i||36494===i||36495===i){if(null===(a=t.get("EXT_texture_compression_bptc")))return null;if(36492===i)return"srgb"===o?a.COMPRESSED_SRGB_ALPHA_BPTC_UNORM_EXT:a.COMPRESSED_RGBA_BPTC_UNORM_EXT;if(36494===i)return a.COMPRESSED_RGB_BPTC_SIGNED_FLOAT_EXT;if(36495===i)return a.COMPRESSED_RGB_BPTC_UNSIGNED_FLOAT_EXT}if(36283===i||36284===i||36285===i||36286===i){if(null===(a=t.get("EXT_texture_compression_rgtc")))return null;if(36283===i)return a.COMPRESSED_RED_RGTC1_EXT;if(36284===i)return a.COMPRESSED_SIGNED_RED_RGTC1_EXT;if(36285===i)return a.COMPRESSED_RED_GREEN_RGTC2_EXT;if(36286===i)return a.COMPRESSED_SIGNED_RED_GREEN_RGTC2_EXT}return 1020===i?e.UNSIGNED_INT_24_8:void 0!==e[i]?e[i]:null}}}let tM=`
void main() {

	gl_Position = vec4( position, 1.0 );

}`,tT=`
uniform sampler2DArray depthColor;
uniform float depthWidth;
uniform float depthHeight;

void main() {

	vec2 coord = vec2( gl_FragCoord.x / depthWidth, gl_FragCoord.y / depthHeight );

	if ( coord.x >= 1.0 ) {

		gl_FragDepth = texture( depthColor, vec3( coord.x - 1.0, coord.y, 1 ) ).r;

	} else {

		gl_FragDepth = texture( depthColor, vec3( coord.x, coord.y, 0 ) ).r;

	}

}`;let WebXRDepthSensing=class WebXRDepthSensing{init(e,t){if(null===this.texture){let i=new r.rjZ(e.texture);(e.depthNear!==t.depthNear||e.depthFar!==t.depthFar)&&(this.depthNear=e.depthNear,this.depthFar=e.depthFar),this.texture=i}}getMesh(e){if(null!==this.texture&&null===this.mesh){let t=e.cameras[0].viewport,i=new r.BKk({vertexShader:tM,fragmentShader:tT,uniforms:{depthColor:{value:this.texture},depthWidth:{value:t.z},depthHeight:{value:t.w}}});this.mesh=new r.eaF(new r.bdM(20,20),i)}return this.mesh}reset(){this.texture=null,this.mesh=null}getDepthTexture(){return this.texture}constructor(){this.texture=null,this.mesh=null,this.depthNear=0,this.depthFar=0}};let WebXRManager=class WebXRManager extends r.Qev{constructor(e,t){super();let i=this,n=null,o=1,l=null,s="local-floor",c=1,d=null,u=null,f=null,p=null,m=null,h=null,_="u">typeof XRWebGLBinding,g=new WebXRDepthSensing,v={},S=t.getContextAttributes(),E=null,M=null,T=[],x=[],R=new r.I9Y,A=null,b=null,C=new r.ubm;C.viewport=new r.IUQ;let P=new r.ubm;P.viewport=new r.IUQ;let L=[C,P],w=new r.nZQ,U=null,D=null;function I(e){let t=x.indexOf(e.inputSource);if(-1===t)return;let i=T[t];void 0!==i&&(i.update(e.inputSource,e.frame,d||l),i.dispatchEvent({type:e.type,data:e.inputSource}))}function N(){n.removeEventListener("select",I),n.removeEventListener("selectstart",I),n.removeEventListener("selectend",I),n.removeEventListener("squeeze",I),n.removeEventListener("squeezestart",I),n.removeEventListener("squeezeend",I),n.removeEventListener("end",N),n.removeEventListener("inputsourceschange",y);for(let e=0;e<T.length;e++){let t=x[e];null!==t&&(x[e]=null,T[e].disconnect(t))}for(let e in U=null,D=null,g.reset(),v)delete v[e];if(e.setRenderTarget(E),m=null,p=null,f=null,n=null,M=null,H.stop(),i.isPresenting=!1,e.setPixelRatio(A),e.setSize(R.width,R.height,!1),null!==b){let e=b.camera;e.fov=b.fov,e.zoom=b.zoom,e.updateProjectionMatrix(),b=null}i.dispatchEvent({type:"sessionend"})}function y(e){for(let t=0;t<e.removed.length;t++){let i=e.removed[t],r=x.indexOf(i);r>=0&&(x[r]=null,T[r].disconnect(i))}for(let t=0;t<e.added.length;t++){let i=e.added[t],r=x.indexOf(i);if(-1===r){for(let e=0;e<T.length;e++)if(e>=x.length){x.push(i),r=e;break}else if(null===x[e]){x[e]=i,r=e;break}if(-1===r)break}let a=T[r];a&&a.connect(i)}}this.cameraAutoUpdate=!0,this.enabled=!1,this.isPresenting=!1,this.getController=function(e){let t=T[e];return void 0===t&&(t=new r.R3r,T[e]=t),t.getTargetRaySpace()},this.getControllerGrip=function(e){let t=T[e];return void 0===t&&(t=new r.R3r,T[e]=t),t.getGripSpace()},this.getHand=function(e){let t=T[e];return void 0===t&&(t=new r.R3r,T[e]=t),t.getHandSpace()},this.setFramebufferScaleFactor=function(e){o=e,!0===i.isPresenting&&(0,r.R8M)("WebXRManager: Cannot change framebuffer scale while presenting.")},this.setReferenceSpaceType=function(e){s=e,!0===i.isPresenting&&(0,r.R8M)("WebXRManager: Cannot change reference space type while presenting.")},this.getReferenceSpace=function(){return d||l},this.setReferenceSpace=function(e){d=e},this.getBaseLayer=function(){return null!==p?p:m},this.getBinding=function(){return null===f&&_&&(f=new XRWebGLBinding(n,t)),f},this.getFrame=function(){return h},this.getSession=function(){return n},this.setSession=async function(a){if(null!==(n=a)){if(E=e.getRenderTarget(),n.addEventListener("select",I),n.addEventListener("selectstart",I),n.addEventListener("selectend",I),n.addEventListener("squeeze",I),n.addEventListener("squeezestart",I),n.addEventListener("squeezeend",I),n.addEventListener("end",N),n.addEventListener("inputsourceschange",y),!0!==S.xrCompatible&&await t.makeXRCompatible(),A=e.getPixelRatio(),e.getSize(R),_&&"createProjectionLayer"in XRWebGLBinding.prototype){let i=null,a=null,l=null;S.depth&&(l=S.stencil?t.DEPTH24_STENCIL8:t.DEPTH_COMPONENT24,i=S.stencil?1027:1026,a=S.stencil?1020:1014);let s={colorFormat:t.RGBA8,depthFormat:l,scaleFactor:o};p=(f=this.getBinding()).createProjectionLayer(s),n.updateRenderState({layers:[p]}),e.setPixelRatio(1),e.setSize(p.textureWidth,p.textureHeight,!1),M=new r.nWS(p.textureWidth,p.textureHeight,{format:1023,type:1009,depthTexture:new r.VCu(p.textureWidth,p.textureHeight,a,void 0,void 0,void 0,void 0,void 0,void 0,i),stencilBuffer:S.stencil,colorSpace:e.outputColorSpace,samples:4*!!S.antialias,resolveDepthBuffer:!1===p.ignoreDepthValues,resolveStencilBuffer:!1===p.ignoreDepthValues,storeMultisampledDepthBuffer:!1===p.ignoreDepthValues,storeMultisampledStencilBuffer:!1===p.ignoreDepthValues})}else{let i={antialias:S.antialias,alpha:!0,depth:S.depth,stencil:S.stencil,framebufferScaleFactor:o};m=new XRWebGLLayer(n,t,i),n.updateRenderState({baseLayer:m}),e.setPixelRatio(1),e.setSize(m.framebufferWidth,m.framebufferHeight,!1),M=new r.nWS(m.framebufferWidth,m.framebufferHeight,{format:1023,type:1009,colorSpace:e.outputColorSpace,stencilBuffer:S.stencil,resolveDepthBuffer:!1===m.ignoreDepthValues,resolveStencilBuffer:!1===m.ignoreDepthValues,storeMultisampledDepthBuffer:!1===m.ignoreDepthValues,storeMultisampledStencilBuffer:!1===m.ignoreDepthValues})}M.isXRRenderTarget=!0,this.setFoveation(c),d=null,l=await n.requestReferenceSpace(s),H.setContext(n),H.start(),i.isPresenting=!0,i.dispatchEvent({type:"sessionstart"})}},this.getEnvironmentBlendMode=function(){if(null!==n)return n.environmentBlendMode},this.getDepthTexture=function(){return g.getDepthTexture()};let O=new r.Pq0,F=new r.Pq0;function B(e,t){null===t?e.matrixWorld.copy(e.matrix):e.matrixWorld.multiplyMatrices(t.matrixWorld,e.matrix),e.matrixWorldInverse.copy(e.matrixWorld).invert()}this.updateCamera=function(e){var t,i,a;if(null===n)return;let o=e.near,l=e.far;null!==g.texture&&(g.depthNear>0&&(o=g.depthNear),g.depthFar>0&&(l=g.depthFar)),w.near=P.near=C.near=o,w.far=P.far=C.far=l,(U!==w.near||D!==w.far)&&(n.updateRenderState({depthNear:w.near,depthFar:w.far}),U=w.near,D=w.far),w.layers.mask=6|e.layers.mask,C.layers.mask=-5&w.layers.mask,P.layers.mask=-3&w.layers.mask;let s=e.parent,c=w.cameras;B(w,s);for(let e=0;e<c.length;e++)B(c[e],s);2===c.length?function(e,t,i){O.setFromMatrixPosition(t.matrixWorld),F.setFromMatrixPosition(i.matrixWorld);let r=O.distanceTo(F),a=t.projectionMatrix.elements,n=i.projectionMatrix.elements,o=a[14]/(a[10]-1),l=a[14]/(a[10]+1),s=(a[9]+1)/a[5],c=(a[9]-1)/a[5],d=(a[8]-1)/a[0],u=(n[8]+1)/n[0],f=r/(-d+u),p=-(f*d);if(t.matrixWorld.decompose(e.position,e.quaternion,e.scale),e.translateX(p),e.translateZ(f),e.matrixWorld.compose(e.position,e.quaternion,e.scale),e.matrixWorldInverse.copy(e.matrixWorld).invert(),-1===a[10])e.projectionMatrix.copy(t.projectionMatrix),e.projectionMatrixInverse.copy(t.projectionMatrixInverse);else{let t=o+f,i=l+f;e.projectionMatrix.makePerspective(o*d-p,o*u+(r-p),s*l/i*t,c*l/i*t,t,i),e.projectionMatrixInverse.copy(e.projectionMatrix).invert()}}(w,C,P):w.projectionMatrix.copy(C.projectionMatrix),null===b&&e.isPerspectiveCamera&&(b={camera:e,fov:e.fov,zoom:e.zoom}),t=e,i=w,null===(a=s)?t.matrix.copy(i.matrixWorld):(t.matrix.copy(a.matrixWorld),t.matrix.invert(),t.matrix.multiply(i.matrixWorld)),t.matrix.decompose(t.position,t.quaternion,t.scale),t.updateMatrixWorld(!0),t.projectionMatrix.copy(i.projectionMatrix),t.projectionMatrixInverse.copy(i.projectionMatrixInverse),t.isPerspectiveCamera&&(t.fov=2*r.a55*Math.atan(1/t.projectionMatrix.elements[5]),t.zoom=1)},this.getCamera=function(){return w},this.getFoveation=function(){if(null!==p||null!==m)return c},this.setFoveation=function(e){c=e,null!==p&&(p.fixedFoveation=e),null!==m&&void 0!==m.fixedFoveation&&(m.fixedFoveation=e)},this.hasDepthSensing=function(){return null!==g.texture},this.getDepthSensingMesh=function(){return g.getMesh(w)},this.getCameraTexture=function(e){return v[e]};let G=null,H=new a;H.setAnimationLoop(function(t,a){if(u=a.getViewerPose(d||l),h=a,null!==u){let t=u.views;null!==m&&(e.setRenderTargetFramebuffer(M,m.framebuffer),e.setRenderTarget(M));let a=!1;t.length!==w.cameras.length&&(w.cameras.length=0,a=!0);for(let i=0;i<t.length;i++){let n=t[i],o=null;if(null!==m)o=m.getViewport(n);else{let t=f.getViewSubImage(p,n);o=t.viewport,0===i&&(e.setRenderTargetTextures(M,t.colorTexture,t.depthStencilTexture),e.setRenderTarget(M))}let l=L[i];void 0===l&&((l=new r.ubm).layers.enable(i),l.viewport=new r.IUQ,L[i]=l),l.matrix.fromArray(n.transform.matrix),l.matrix.decompose(l.position,l.quaternion,l.scale),l.projectionMatrix.fromArray(n.projectionMatrix),l.projectionMatrixInverse.copy(l.projectionMatrix).invert(),l.viewport.set(o.x,o.y,o.width,o.height),0===i&&(w.matrix.copy(l.matrix),w.matrix.decompose(w.position,w.quaternion,w.scale)),!0===a&&w.cameras.push(l)}let o=n.enabledFeatures;if(o&&o.includes("depth-sensing")&&"gpu-optimized"==n.depthUsage&&_){let e=(f=i.getBinding()).getDepthInformation(t[0]);e&&e.isValid&&e.texture&&g.init(e,n.renderState)}if(o&&o.includes("camera-access")&&_){e.state.unbindTexture(),f=i.getBinding();for(let e=0;e<t.length;e++){let i=t[e].camera;if(i){let e=v[i];e||(e=new r.rjZ,v[i]=e);let t=f.getCameraImage(i);e.sourceTexture=t}}}}for(let e=0;e<T.length;e++){let t=x[e],i=T[e];null!==t&&void 0!==i&&i.update(t,a,d||l)}G&&G(t,a),a.detectedPlanes&&i.dispatchEvent({type:"planesdetected",data:a}),h=null}),this.setAnimationLoop=function(e){G=e},this.dispose=function(){}}};let tx=new r.kn4,tR=new r.dwI;function tA(e,t){function i(e,t){!0===e.matrixAutoUpdate&&e.updateMatrix(),t.value.copy(e.matrix)}function a(e,r){e.opacity.value=r.opacity,r.color&&e.diffuse.value.copy(r.color),r.emissive&&e.emissive.value.copy(r.emissive).multiplyScalar(r.emissiveIntensity),r.map&&(e.map.value=r.map,i(r.map,e.mapTransform)),r.alphaMap&&(e.alphaMap.value=r.alphaMap,i(r.alphaMap,e.alphaMapTransform)),r.bumpMap&&(e.bumpMap.value=r.bumpMap,i(r.bumpMap,e.bumpMapTransform),e.bumpScale.value=r.bumpScale,1===r.side&&(e.bumpScale.value*=-1)),r.normalMap&&(e.normalMap.value=r.normalMap,i(r.normalMap,e.normalMapTransform),e.normalScale.value.copy(r.normalScale),1===r.side&&e.normalScale.value.negate()),r.displacementMap&&(e.displacementMap.value=r.displacementMap,i(r.displacementMap,e.displacementMapTransform),e.displacementScale.value=r.displacementScale,e.displacementBias.value=r.displacementBias),r.emissiveMap&&(e.emissiveMap.value=r.emissiveMap,i(r.emissiveMap,e.emissiveMapTransform)),r.specularMap&&(e.specularMap.value=r.specularMap,i(r.specularMap,e.specularMapTransform)),r.alphaTest>0&&(e.alphaTest.value=r.alphaTest);let a=t.get(r),n=a.envMap,o=a.envMapRotation;n&&(e.envMap.value=n,e.envMapRotation.value.setFromMatrix4(tx.makeRotationFromEuler(o)).transpose(),n.isCubeTexture&&!1===n.isRenderTargetTexture&&e.envMapRotation.value.premultiply(tR),e.reflectivity.value=r.reflectivity,e.ior.value=r.ior,e.refractionRatio.value=r.refractionRatio),r.lightMap&&(e.lightMap.value=r.lightMap,e.lightMapIntensity.value=r.lightMapIntensity,i(r.lightMap,e.lightMapTransform)),r.aoMap&&(e.aoMap.value=r.aoMap,e.aoMapIntensity.value=r.aoMapIntensity,i(r.aoMap,e.aoMapTransform))}return{refreshFogUniforms:function(t,i){i.color.getRGB(t.fogColor.value,(0,r._Ut)(e)),i.isFog?(t.fogNear.value=i.near,t.fogFar.value=i.far):i.isFogExp2&&(t.fogDensity.value=i.density)},refreshMaterialUniforms:function(e,r,n,o,l){var s,c,d,u,f,p,m,h,_,g,v,S,E,M,T,x,R,A,b,C,P,L,w;let U;r.isNodeMaterial?r.uniformsNeedUpdate=!1:r.isMeshBasicMaterial?a(e,r):r.isMeshLambertMaterial?(a(e,r),r.envMap&&(e.envMapIntensity.value=r.envMapIntensity)):r.isMeshToonMaterial?(a(e,r),s=e,(c=r).gradientMap&&(s.gradientMap.value=c.gradientMap)):r.isMeshPhongMaterial?(a(e,r),d=e,u=r,d.specular.value.copy(u.specular),d.shininess.value=Math.max(u.shininess,1e-4),r.envMap&&(e.envMapIntensity.value=r.envMapIntensity)):r.isMeshStandardMaterial?(a(e,r),f=e,p=r,f.metalness.value=p.metalness,p.metalnessMap&&(f.metalnessMap.value=p.metalnessMap,i(p.metalnessMap,f.metalnessMapTransform)),f.roughness.value=p.roughness,p.roughnessMap&&(f.roughnessMap.value=p.roughnessMap,i(p.roughnessMap,f.roughnessMapTransform)),p.envMap&&(f.envMapIntensity.value=p.envMapIntensity),r.isMeshPhysicalMaterial&&(m=e,h=r,_=l,m.ior.value=h.ior,h.sheen>0&&(m.sheenColor.value.copy(h.sheenColor).multiplyScalar(h.sheen),m.sheenRoughness.value=h.sheenRoughness,h.sheenColorMap&&(m.sheenColorMap.value=h.sheenColorMap,i(h.sheenColorMap,m.sheenColorMapTransform)),h.sheenRoughnessMap&&(m.sheenRoughnessMap.value=h.sheenRoughnessMap,i(h.sheenRoughnessMap,m.sheenRoughnessMapTransform))),h.clearcoat>0&&(m.clearcoat.value=h.clearcoat,m.clearcoatRoughness.value=h.clearcoatRoughness,h.clearcoatMap&&(m.clearcoatMap.value=h.clearcoatMap,i(h.clearcoatMap,m.clearcoatMapTransform)),h.clearcoatRoughnessMap&&(m.clearcoatRoughnessMap.value=h.clearcoatRoughnessMap,i(h.clearcoatRoughnessMap,m.clearcoatRoughnessMapTransform)),h.clearcoatNormalMap&&(m.clearcoatNormalMap.value=h.clearcoatNormalMap,i(h.clearcoatNormalMap,m.clearcoatNormalMapTransform),m.clearcoatNormalScale.value.copy(h.clearcoatNormalScale),1===h.side&&m.clearcoatNormalScale.value.negate())),h.dispersion>0&&(m.dispersion.value=h.dispersion),h.retroreflectivity>0&&(m.retroreflectivity.value=h.retroreflectivity),h.iridescence>0&&(m.iridescence.value=h.iridescence,m.iridescenceIOR.value=h.iridescenceIOR,m.iridescenceThicknessMinimum.value=h.iridescenceThicknessRange[0],m.iridescenceThicknessMaximum.value=h.iridescenceThicknessRange[1],h.iridescenceMap&&(m.iridescenceMap.value=h.iridescenceMap,i(h.iridescenceMap,m.iridescenceMapTransform)),h.iridescenceThicknessMap&&(m.iridescenceThicknessMap.value=h.iridescenceThicknessMap,i(h.iridescenceThicknessMap,m.iridescenceThicknessMapTransform))),h.transmission>0&&(m.transmission.value=h.transmission,m.transmissionSamplerMap.value=_.texture,m.transmissionSamplerSize.value.set(_.width,_.height),h.transmissionMap&&(m.transmissionMap.value=h.transmissionMap,i(h.transmissionMap,m.transmissionMapTransform)),m.thickness.value=h.thickness,h.thicknessMap&&(m.thicknessMap.value=h.thicknessMap,i(h.thicknessMap,m.thicknessMapTransform)),m.attenuationDistance.value=h.attenuationDistance,m.attenuationColor.value.copy(h.attenuationColor)),h.anisotropy>0&&(m.anisotropyVector.value.set(h.anisotropy*Math.cos(h.anisotropyRotation),h.anisotropy*Math.sin(h.anisotropyRotation)),h.anisotropyMap&&(m.anisotropyMap.value=h.anisotropyMap,i(h.anisotropyMap,m.anisotropyMapTransform))),m.specularIntensity.value=h.specularIntensity,m.specularColor.value.copy(h.specularColor),h.specularColorMap&&(m.specularColorMap.value=h.specularColorMap,i(h.specularColorMap,m.specularColorMapTransform)),h.specularIntensityMap&&(m.specularIntensityMap.value=h.specularIntensityMap,i(h.specularIntensityMap,m.specularIntensityMapTransform)))):r.isMeshMatcapMaterial?(a(e,r),g=e,(v=r).matcap&&(g.matcap.value=v.matcap)):r.isMeshDepthMaterial?a(e,r):r.isMeshDistanceMaterial?(a(e,r),S=e,E=r,U=t.get(E).light,S.referencePosition.value.setFromMatrixPosition(U.matrixWorld),S.nearDistance.value=U.shadow.camera.near,S.farDistance.value=U.shadow.camera.far):r.isMeshNormalMaterial?a(e,r):r.isLineBasicMaterial?(M=e,T=r,M.diffuse.value.copy(T.color),M.opacity.value=T.opacity,T.map&&(M.map.value=T.map,i(T.map,M.mapTransform)),r.isLineDashedMaterial&&(x=e,R=r,x.dashSize.value=R.dashSize,x.totalSize.value=R.dashSize+R.gapSize,x.scale.value=R.scale)):r.isPointsMaterial?(A=e,b=r,C=n,P=o,A.diffuse.value.copy(b.color),A.opacity.value=b.opacity,A.size.value=b.size*C,A.scale.value=.5*P,b.map&&(A.map.value=b.map,i(b.map,A.uvTransform)),b.alphaMap&&(A.alphaMap.value=b.alphaMap,i(b.alphaMap,A.alphaMapTransform)),b.alphaTest>0&&(A.alphaTest.value=b.alphaTest)):r.isSpriteMaterial?(L=e,w=r,L.diffuse.value.copy(w.color),L.opacity.value=w.opacity,L.rotation.value=w.rotation,w.map&&(L.map.value=w.map,i(w.map,L.mapTransform)),w.alphaMap&&(L.alphaMap.value=w.alphaMap,i(w.alphaMap,L.alphaMapTransform)),w.alphaTest>0&&(L.alphaTest.value=w.alphaTest)):r.isShadowMaterial?(e.color.value.copy(r.color),e.opacity.value=r.opacity):r.isShaderMaterial&&(r.uniformsNeedUpdate=!1)}}}function tb(e,t,i,a){let n={},o={},l=[],s=e.getParameter(e.MAX_UNIFORM_BUFFER_BINDINGS);function c(t,i,r,a){if(!0===function(e,t,i,r){let a=e.value,n=t+"_"+i;if(void 0===r[n])return"number"==typeof a||"boolean"==typeof a?r[n]=a:ArrayBuffer.isView(a)?r[n]=a.slice():r[n]=a.clone(),!0;{let e=r[n];if("number"==typeof a||"boolean"==typeof a){if(e!==a)return r[n]=a,!0}else if(ArrayBuffer.isView(a))return!0;else if(!1===e.equals(a))return e.copy(a),!0}return!1}(t,i,r,a)){let i=t.__offset,r=t.value;if(Array.isArray(r)){let e=0;for(let i=0;i<r.length;i++){let a=r[i],n=u(a);d(a,t.__data,e),"number"==typeof a||"boolean"==typeof a||a.isMatrix3||ArrayBuffer.isView(a)||(e+=n.storage/Float32Array.BYTES_PER_ELEMENT)}}else d(r,t.__data,0);e.bufferSubData(e.UNIFORM_BUFFER,i,t.__data)}}function d(e,t,i){"number"==typeof e||"boolean"==typeof e?t[0]=e:e.isMatrix3?(t[0]=e.elements[0],t[1]=e.elements[1],t[2]=e.elements[2],t[3]=0,t[4]=e.elements[3],t[5]=e.elements[4],t[6]=e.elements[5],t[7]=0,t[8]=e.elements[6],t[9]=e.elements[7],t[10]=e.elements[8],t[11]=0):ArrayBuffer.isView(e)?t.set(new e.constructor(e.buffer,e.byteOffset,t.length)):e.toArray(t,i)}function u(e){let t={boundary:0,storage:0};return"number"==typeof e||"boolean"==typeof e?(t.boundary=4,t.storage=4):e.isVector2?(t.boundary=8,t.storage=8):e.isVector3||e.isColor?(t.boundary=16,t.storage=12):e.isVector4?(t.boundary=16,t.storage=16):e.isMatrix3?(t.boundary=48,t.storage=48):e.isMatrix4?(t.boundary=64,t.storage=64):e.isTexture?(0,r.R8M)("WebGLRenderer: Texture samplers can not be part of an uniforms group."):ArrayBuffer.isView(e)?(t.boundary=16,t.storage=e.byteLength):(0,r.R8M)("WebGLRenderer: Unsupported uniform value type.",e),t}function f(t){let i=t.target;i.removeEventListener("dispose",f);let r=l.indexOf(i.__bindingPointIndex);l.splice(r,1),e.deleteBuffer(n[i.id]),delete n[i.id],delete o[i.id]}return{bind:function(e,t){let i=t.program;a.uniformBlockBinding(e,i)},update:function(i,d){var p;let m,h,_,g,v=n[i.id];void 0===v&&(function(e){let t=e.uniforms,i=0;for(let e=0,r=t.length;e<r;e++){let r=Array.isArray(t[e])?t[e]:[t[e]];for(let e=0,t=r.length;e<t;e++){let t=r[e],a=Array.isArray(t.value)?t.value:[t.value];for(let e=0,r=a.length;e<r;e++){let r=u(a[e]),n=i%16,o=n%r.boundary,l=n+o;i+=o,0!==l&&16-l<r.storage&&(i+=16-l),t.__data=new Float32Array(r.storage/Float32Array.BYTES_PER_ELEMENT),t.__offset=i,i+=r.storage}}}let r=i%16;r>0&&(i+=16-r),e.__size=i,e.__cache={}}(i),(p=i).__bindingPointIndex=m=function(){for(let e=0;e<s;e++)if(-1===l.indexOf(e))return l.push(e),e;return(0,r.z3S)("WebGLRenderer: Maximum number of simultaneously usable uniforms groups reached."),0}(),h=e.createBuffer(),_=p.__size,g=p.usage,e.bindBuffer(e.UNIFORM_BUFFER,h),e.bufferData(e.UNIFORM_BUFFER,_,g),e.bindBuffer(e.UNIFORM_BUFFER,null),e.bindBufferBase(e.UNIFORM_BUFFER,m,h),v=h,n[i.id]=v,i.addEventListener("dispose",f));let S=d.program;a.updateUBOMapping(i,S);let E=t.render.frame;o[i.id]!==E&&(function(t){let i=n[t.id],r=t.uniforms,a=t.__cache;e.bindBuffer(e.UNIFORM_BUFFER,i);for(let e=0,t=r.length;e<t;e++){let t=r[e];if(Array.isArray(t))for(let i=0,r=t.length;i<r;i++)c(t[i],e,i,a);else c(t,e,0,a)}e.bindBuffer(e.UNIFORM_BUFFER,null)}(i),o[i.id]=E)},dispose:function(){for(let t in n)e.deleteBuffer(n[t]);l=[],n={},o={}}}}tR.set(-1,0,0,0,1,0,0,0,1);let tC=new Uint16Array([12469,15057,12620,14925,13266,14620,13807,14376,14323,13990,14545,13625,14713,13328,14840,12882,14931,12528,14996,12233,15039,11829,15066,11525,15080,11295,15085,10976,15082,10705,15073,10495,13880,14564,13898,14542,13977,14430,14158,14124,14393,13732,14556,13410,14702,12996,14814,12596,14891,12291,14937,11834,14957,11489,14958,11194,14943,10803,14921,10506,14893,10278,14858,9960,14484,14039,14487,14025,14499,13941,14524,13740,14574,13468,14654,13106,14743,12678,14818,12344,14867,11893,14889,11509,14893,11180,14881,10751,14852,10428,14812,10128,14765,9754,14712,9466,14764,13480,14764,13475,14766,13440,14766,13347,14769,13070,14786,12713,14816,12387,14844,11957,14860,11549,14868,11215,14855,10751,14825,10403,14782,10044,14729,9651,14666,9352,14599,9029,14967,12835,14966,12831,14963,12804,14954,12723,14936,12564,14917,12347,14900,11958,14886,11569,14878,11247,14859,10765,14828,10401,14784,10011,14727,9600,14660,9289,14586,8893,14508,8533,15111,12234,15110,12234,15104,12216,15092,12156,15067,12010,15028,11776,14981,11500,14942,11205,14902,10752,14861,10393,14812,9991,14752,9570,14682,9252,14603,8808,14519,8445,14431,8145,15209,11449,15208,11451,15202,11451,15190,11438,15163,11384,15117,11274,15055,10979,14994,10648,14932,10343,14871,9936,14803,9532,14729,9218,14645,8742,14556,8381,14461,8020,14365,7603,15273,10603,15272,10607,15267,10619,15256,10631,15231,10614,15182,10535,15118,10389,15042,10167,14963,9787,14883,9447,14800,9115,14710,8665,14615,8318,14514,7911,14411,7507,14279,7198,15314,9675,15313,9683,15309,9712,15298,9759,15277,9797,15229,9773,15166,9668,15084,9487,14995,9274,14898,8910,14800,8539,14697,8234,14590,7790,14479,7409,14367,7067,14178,6621,15337,8619,15337,8631,15333,8677,15325,8769,15305,8871,15264,8940,15202,8909,15119,8775,15022,8565,14916,8328,14804,8009,14688,7614,14569,7287,14448,6888,14321,6483,14088,6171,15350,7402,15350,7419,15347,7480,15340,7613,15322,7804,15287,7973,15229,8057,15148,8012,15046,7846,14933,7611,14810,7357,14682,7069,14552,6656,14421,6316,14251,5948,14007,5528,15356,5942,15356,5977,15353,6119,15348,6294,15332,6551,15302,6824,15249,7044,15171,7122,15070,7050,14949,6861,14818,6611,14679,6349,14538,6067,14398,5651,14189,5311,13935,4958,15359,4123,15359,4153,15356,4296,15353,4646,15338,5160,15311,5508,15263,5829,15188,6042,15088,6094,14966,6001,14826,5796,14678,5543,14527,5287,14377,4985,14133,4586,13869,4257,15360,1563,15360,1642,15358,2076,15354,2636,15341,3350,15317,4019,15273,4429,15203,4732,15105,4911,14981,4932,14836,4818,14679,4621,14517,4386,14359,4156,14083,3795,13808,3437,15360,122,15360,137,15358,285,15355,636,15344,1274,15322,2177,15281,2765,15215,3223,15120,3451,14995,3569,14846,3567,14681,3466,14511,3305,14344,3121,14037,2800,13753,2467,15360,0,15360,1,15359,21,15355,89,15346,253,15325,479,15287,796,15225,1148,15133,1492,15008,1749,14856,1882,14685,1886,14506,1783,14324,1608,13996,1398,13702,1183]),tP=null;let WebGLRenderer=class WebGLRenderer{get coordinateSystem(){return 2e3}get outputColorSpace(){return this._outputColorSpace}set outputColorSpace(e){this._outputColorSpace=e;let t=this.getContext();t.drawingBufferColorSpace=r.ppV._getDrawingBufferColorSpace(e),t.unpackColorSpace=r.ppV._getUnpackColorSpace()}constructor(e={}){let t,i,o,l,s,c,d,u,g,v,S,E,M,T,x,R,A,b,C,P,L,F,G,H,{canvas:V=(0,r.lPF)(),context:W=null,depth:z=!0,stencil:k=!1,alpha:X=!1,antialias:Y=!1,premultipliedAlpha:q=!0,preserveDrawingBuffer:K=!1,powerPreference:j="default",failIfMajorPerformanceCaveat:Z=!1,reversedDepthBuffer:Q=!1,outputBufferType:J=1009}=e;if(this.isWebGLRenderer=!0,null!==W){if("u">typeof WebGLRenderingContext&&W instanceof WebGLRenderingContext)throw Error("THREE.WebGLRenderer: WebGL 1 is not supported since r163.");t=W.getContextAttributes().alpha}else t=X;let $=new Set([1033,1031,1029]),ee=new Set([1009,1014,1012,1020,1017,1018]),et=new Uint32Array(4),ei=new Int32Array(4),er=new r.Pq0,ea=null,en=null,eo=[],el=[],es=null;this.domElement=V,this.debug={checkShaderErrors:!0,diagnostics:{keywords:!1},onShaderError:null},this.autoClear=!0,this.autoClearColor=!0,this.autoClearDepth=!0,this.autoClearStencil=!0,this.sortObjects=!0,this.clippingPlanes=[],this.localClippingEnabled=!1,this.toneMapping=0,this.toneMappingExposure=1,this.transmissionResolutionScale=1;let ec=this,ed=!1,eu=null,ef=null,ep=null,em=null;this._outputColorSpace="srgb";let eh=0,e_=0,eg=null,ev=-1,eS=null,eE=new r.IUQ,eM=new r.IUQ,eT=null,ex=new r.Q1f(0),eR=0,eA=V.width,eb=V.height,eC=1,eP=null,eL=null,ew=new r.IUQ(0,0,eA,eb),eU=new r.IUQ(0,0,eA,eb),eD=!1,eI=new r.PPD,eN=!1,ey=!1,eO=new r.kn4,eF=new r.Pq0,eB=new r.IUQ,eG={background:null,fog:null,environment:null,overrideMaterial:null,isScene:!0},eH=!1;function eV(){return null===eg?eC:1}let eW=W;function ez(e,t){return V.getContext(e,t)}try{if("setAttribute"in V&&V.setAttribute("data-engine","three.js r".concat("186")),V.addEventListener("webglcontextlost",eY,!1),V.addEventListener("webglcontextrestored",eq,!1),V.addEventListener("webglcontextcreationerror",eK,!1),null===eW){let e="webgl2";if(eW=ez(e,{alpha:!0,depth:z,stencil:k,antialias:Y,premultipliedAlpha:q,preserveDrawingBuffer:K,powerPreference:j,failIfMajorPerformanceCaveat:Z}),null===eW)if(ez(e))throw Error("THREE.WebGLRenderer: Error creating WebGL context with your selected attributes.");else throw Error("THREE.WebGLRenderer: Error creating WebGL context.")}ek()}catch(e){throw V.removeEventListener("webglcontextlost",eY,!1),V.removeEventListener("webglcontextrestored",eq,!1),V.removeEventListener("webglcontextcreationerror",eK,!1),(0,r.z3S)("WebGLRenderer: "+e.message),e}function ek(){(i=new U(eW)).init(),F=new tE(eW,i),o=new h(eW,i,e,F),l=new tv(eW,i),o.reversedDepthBuffer&&Q&&l.buffers.depth.setReversed(!0),ef=eW.createFramebuffer(),ep=eW.createFramebuffer(),em=eW.createFramebuffer(),s=new N(eW),c=new e7,d=new tS(eW,i,l,c,o,F,s),u=new w(ec),g=new n(eW),G=new p(eW,g),v=new D(eW,g,s,G),S=new O(eW,v,g,G,s),C=new y(eW,o,d),R=new _(c),E=new e9(ec,u,i,o,G,R),M=new tA(ec,c),T=new tr,x=new tc(i),b=new f(ec,u,l,S,t,q),A=new tg(ec,S,o),H=new tb(eW,s,o,l),P=new m(eW,i,s),L=new I(eW,i,s),s.programs=E.programs,ec.capabilities=o,ec.extensions=i,ec.properties=c,ec.renderLists=T,ec.shadowMap=A,ec.state=l,ec.info=s}1009!==J&&(es=new B(J,V.width,V.height,Y,z,k));let eX=new WebXRManager(ec,eW);function eY(e){e.preventDefault(),(0,r.Rm2)("WebGLRenderer: Context Lost."),ed=!0}function eq(){(0,r.Rm2)("WebGLRenderer: Context Restored."),ed=!1;let e=s.autoReset,t=A.enabled,i=A.autoUpdate,a=A.needsUpdate,n=A.type;ek(),s.autoReset=e,A.enabled=t,A.autoUpdate=i,A.needsUpdate=a,A.type=n}function eK(e){(0,r.z3S)("WebGLRenderer: A WebGL context could not be created. Reason: ",e.statusMessage)}function ej(e){var t,i;let r,a=e.target;a.removeEventListener("dispose",ej),i=t=a,void 0!==(r=c.get(i).programs)&&(r.forEach(function(e){E.releaseProgram(e)}),i.isShaderMaterial&&E.releaseShaderCache(i)),c.remove(t)}function eZ(e,t,i,r){null!==eu&&e.isNodeMaterial&&eu.setObject(r,e),!0===eN&&R.setState(e,i,!1),!0===e.transparent&&2===e.side&&!1===e.forceSinglePass?(e.side=1,e.needsUpdate=!0,e6(e,t,r),e.side=0,e.needsUpdate=!0,e6(e,t,r),e.side=2):e6(e,t,r)}this.xr=eX,this.getContext=function(){return eW},this.getContextAttributes=function(){return eW.getContextAttributes()},this.forceContextLoss=function(){let e=i.get("WEBGL_lose_context");e&&e.loseContext()},this.forceContextRestore=function(){let e=i.get("WEBGL_lose_context");e&&e.restoreContext()},this.getPixelRatio=function(){return eC},this.setPixelRatio=function(e){void 0!==e&&(eC=e,this.setSize(eA,eb,!1))},this.getSize=function(e){return e.set(eA,eb)},this.setSize=function(e,t){let i=!(arguments.length>2)||void 0===arguments[2]||arguments[2];eX.isPresenting?(0,r.R8M)("WebGLRenderer: Can't change size while VR device is presenting."):(eA=e,eb=t,V.width=Math.floor(e*eC),V.height=Math.floor(t*eC),!0===i&&(V.style.width=e+"px",V.style.height=t+"px"),null!==es&&es.setSize(V.width,V.height),this.setViewport(0,0,e,t))},this.getDrawingBufferSize=function(e){return e.set(eA*eC,eb*eC).floor()},this.setDrawingBufferSize=function(e,t,i){eA=e,eb=t,eC=i,V.width=Math.floor(e*i),V.height=Math.floor(t*i),this.setViewport(0,0,e,t)},this.setEffects=function(e){if(1009===J)return void(0,r.z3S)("WebGLRenderer: setEffects() requires outputBufferType set to HalfFloatType or FloatType.");if(e){for(let t=0;t<e.length;t++)if(!0===e[t].isOutputPass){(0,r.R8M)("WebGLRenderer: OutputPass is not needed in setEffects(). Tone mapping and color space conversion are applied automatically.");break}}es.setEffects(e||[])},this.getCurrentViewport=function(e){return e.copy(eE)},this.getViewport=function(e){return e.copy(ew)},this.setViewport=function(e,t,i,r){e.isVector4?ew.set(e.x,e.y,e.z,e.w):ew.set(e,t,i,r),l.viewport(eE.copy(ew).multiplyScalar(eC).round())},this.getScissor=function(e){return e.copy(eU)},this.setScissor=function(e,t,i,r){e.isVector4?eU.set(e.x,e.y,e.z,e.w):eU.set(e,t,i,r),l.scissor(eM.copy(eU).multiplyScalar(eC).round())},this.getScissorTest=function(){return eD},this.setScissorTest=function(e){l.setScissorTest(eD=e)},this.setOpaqueSort=function(e){eP=e},this.setTransparentSort=function(e){eL=e},this.getClearColor=function(e){return e.copy(b.getClearColor())},this.setClearColor=function(){b.setClearColor(...arguments)},this.getClearAlpha=function(){return b.getClearAlpha()},this.setClearAlpha=function(){b.setClearAlpha(...arguments)},this.clear=function(){let e=!(arguments.length>0)||void 0===arguments[0]||arguments[0],t=!(arguments.length>1)||void 0===arguments[1]||arguments[1],i=!(arguments.length>2)||void 0===arguments[2]||arguments[2],r=0;if(e){let e=!1;if(null!==eg){let t=eg.texture.format;e=$.has(t)}if(e){let e=eg.texture.type,t=ee.has(e),i=b.getClearColor(),r=b.getClearAlpha(),a=i.r,n=i.g,o=i.b;t?(et[0]=a,et[1]=n,et[2]=o,et[3]=r,eW.clearBufferuiv(eW.COLOR,0,et)):(ei[0]=a,ei[1]=n,ei[2]=o,ei[3]=r,eW.clearBufferiv(eW.COLOR,0,ei))}else r|=eW.COLOR_BUFFER_BIT}t&&(r|=eW.DEPTH_BUFFER_BIT,this.state.buffers.depth.setMask(!0)),i&&(r|=eW.STENCIL_BUFFER_BIT,this.state.buffers.stencil.setMask(0xffffffff)),0!==r&&eW.clear(r)},this.clearColor=function(){this.clear(!0,!1,!1)},this.clearDepth=function(){this.clear(!1,!0,!1)},this.clearStencil=function(){this.clear(!1,!1,!0)},this.setNodesHandler=function(e){e.setRenderer(this),eu=e},this.dispose=function(){V.removeEventListener("webglcontextlost",eY,!1),V.removeEventListener("webglcontextrestored",eq,!1),V.removeEventListener("webglcontextcreationerror",eK,!1),b.dispose(),T.dispose(),x.dispose(),c.dispose(),u.dispose(),S.dispose(),G.dispose(),H.dispose(),E.dispose(),eX.dispose(),eX.removeEventListener("sessionstart",eJ),eX.removeEventListener("sessionend",e$),e0.stop()},this.renderBufferDirect=function(e,t,a,n,s,f){let p;null===t&&(t=eG);let m=s.isMesh&&0>s.matrixWorld.determinantAffine(),h=function(e,t,i,a,n){!0!==t.isScene&&(t=eG),d.resetTextureUnits();let s=t.fog,f=a.isMeshStandardMaterial||a.isMeshLambertMaterial||a.isMeshPhongMaterial?t.environment:null,p=null===eg?ec.outputColorSpace:!0===eg.isXRRenderTarget?eg.texture.colorSpace:r.ppV.workingColorSpace,m=a.isMeshStandardMaterial||a.isMeshLambertMaterial&&!a.envMap||a.isMeshPhongMaterial&&!a.envMap,h=u.get(a.envMap||f,m),_=!0===a.vertexColors&&!!i.attributes.color&&4===i.attributes.color.itemSize,g=!!i.attributes.tangent&&(!!a.normalMap||a.anisotropy>0),v=!!i.morphAttributes.position,S=!!i.morphAttributes.normal,E=!!i.morphAttributes.color,T=0;a.toneMapped&&(null===eg||!0===eg.isXRRenderTarget)&&(T=ec.toneMapping);let x=i.morphAttributes.position||i.morphAttributes.normal||i.morphAttributes.color,A=void 0!==x?x.length:0,b=c.get(a),P=en.state.lights;if(!0===eN&&(!0===ey||e!==eS)){let t=e===eS&&a.id===ev;R.setState(a,e,t)}let L=!1;a.version===b.__version?b.needsLights&&b.lightsStateVersion!==P.state.version||b.outputColorSpace!==p||n.isBatchedMesh&&!1===b.batching?L=!0:n.isBatchedMesh||!0!==b.batching?n.isBatchedMesh&&!0===b.batchingColor&&null===n._colorsTexture||n.isBatchedMesh&&!1===b.batchingColor&&null!==n._colorsTexture||n.isInstancedMesh&&!1===b.instancing?L=!0:n.isInstancedMesh||!0!==b.instancing?n.isSkinnedMesh&&!1===b.skinning?L=!0:n.isSkinnedMesh||!0!==b.skinning?n.isInstancedMesh&&!0===b.instancingColor&&null===n.instanceColor||n.isInstancedMesh&&!1===b.instancingColor&&null!==n.instanceColor||n.isInstancedMesh&&!0===b.instancingMorph&&null===n.morphTexture||n.isInstancedMesh&&!1===b.instancingMorph&&null!==n.morphTexture||b.envMap!==h||!0===a.fog&&b.fog!==s||void 0!==b.numClippingPlanes&&(b.numClippingPlanes!==R.numPlanes||b.numIntersection!==R.numIntersection)||b.vertexAlphas!==_||b.vertexTangents!==g||b.morphTargets!==v||b.morphNormals!==S||b.morphColors!==E||b.toneMapping!==T||b.morphTargetsCount!==A?L=!0:!!b.lightProbeGrid!=en.state.lightProbeGridArray.length>0&&(L=!0):L=!0:L=!0:L=!0:(L=!0,b.__version=a.version);let w=b.currentProgram;!0===L&&(w=e6(a,t,n),eu&&a.isNodeMaterial&&eu.onUpdateProgram(a,w,b));let U=!1,D=!1,I=!1,N=w.getUniforms(),y=b.uniforms;if(l.useProgram(w.program)&&(U=!0,D=!0,I=!0),a.id!==ev&&(ev=a.id,D=!0),b.needsLights){let e=function(e,t){if(0===e.length)return null;if(1===e.length)return null!==e[0].texture?e[0]:null;er.setFromMatrixPosition(t.matrixWorld);for(let t=0,i=e.length;t<i;t++){let i=e[t];if(null!==i.texture&&i.boundingBox.containsPoint(er))return i}return null}(en.state.lightProbeGridArray,n);b.lightProbeGrid!==e&&(b.lightProbeGrid=e,D=!0)}if(U||eS!==e){l.buffers.depth.getReversed()&&!0!==e.reversedDepth&&(e._reversedDepth=!0,e.updateProjectionMatrix()),N.setValue(eW,"projectionMatrix",e.projectionMatrix),N.setValue(eW,"viewMatrix",e.matrixWorldInverse);let t=N.map.cameraPosition;void 0!==t&&t.setValue(eW,eF.setFromMatrixPosition(e.matrixWorld)),o.logarithmicDepthBuffer&&N.setValue(eW,"logDepthBufFC",2/(Math.log(e.far+1)/Math.LN2)),(a.isMeshPhongMaterial||a.isMeshToonMaterial||a.isMeshLambertMaterial||a.isMeshBasicMaterial||a.isMeshStandardMaterial||a.isShaderMaterial)&&N.setValue(eW,"isOrthographic",!0===e.isOrthographicCamera),eS!==e&&(eS=e,D=!0,I=!0)}if(b.needsLights&&(P.state.sunShadowMap.length>0&&N.setValue(eW,"sunShadowMap",P.state.sunShadowMap,d),P.state.directionalShadowMap.length>0&&N.setValue(eW,"directionalShadowMap",P.state.directionalShadowMap,d),P.state.spotShadowMap.length>0&&N.setValue(eW,"spotShadowMap",P.state.spotShadowMap,d),P.state.pointShadowMap.length>0&&N.setValue(eW,"pointShadowMap",P.state.pointShadowMap,d)),n.isSkinnedMesh){N.setOptional(eW,n,"bindMatrix"),N.setOptional(eW,n,"bindMatrixInverse");let e=n.skeleton;e&&(null===e.boneTexture&&e.computeBoneTexture(),N.setValue(eW,"boneTexture",e.boneTexture,d))}n.isBatchedMesh&&(N.setOptional(eW,n,"batchingTexture"),N.setValue(eW,"batchingTexture",n._matricesTexture,d),N.setOptional(eW,n,"batchingIdTexture"),N.setValue(eW,"batchingIdTexture",n._indirectTexture,d),N.setOptional(eW,n,"batchingColorTexture"),null!==n._colorsTexture&&N.setValue(eW,"batchingColorTexture",n._colorsTexture,d));let O=i.morphAttributes;if((void 0!==O.position||void 0!==O.normal||void 0!==O.color)&&C.update(n,i,w),(D||b.receiveShadow!==n.receiveShadow)&&(b.receiveShadow=n.receiveShadow,N.setValue(eW,"receiveShadow",n.receiveShadow)),(a.isMeshStandardMaterial||a.isMeshLambertMaterial||a.isMeshPhongMaterial)&&null===a.envMap&&null!==t.environment&&(y.envMapIntensity.value=t.environmentIntensity),void 0!==y.dfgLUT&&(y.dfgLUT.value=(null===tP&&((tP=new r.GYF(tC,16,16,1030,1016)).name="DFG_LUT",tP.minFilter=1006,tP.magFilter=1006,tP.wrapS=1001,tP.wrapT=1001,tP.generateMipmaps=!1,tP.needsUpdate=!0),tP)),D){var F,B;if(N.setValue(eW,"toneMappingExposure",ec.toneMappingExposure),b.needsLights&&(F=y,B=I,F.ambientLightColor.needsUpdate=B,F.lightProbe.needsUpdate=B,F.sunLights.needsUpdate=B,F.sunLightShadows.needsUpdate=B,F.directionalLights.needsUpdate=B,F.directionalLightShadows.needsUpdate=B,F.pointLights.needsUpdate=B,F.pointLightShadows.needsUpdate=B,F.spotLights.needsUpdate=B,F.spotLightShadows.needsUpdate=B,F.rectAreaLights.needsUpdate=B,F.hemisphereLights.needsUpdate=B),s&&!0===a.fog&&M.refreshFogUniforms(y,s),M.refreshMaterialUniforms(y,a,eC,eb,en.state.transmissionRenderTarget[e.id]),b.needsLights&&b.lightProbeGrid){let e=b.lightProbeGrid;y.probesSH.value=e.texture,y.probesMin.value.copy(e.boundingBox.min),y.probesMax.value.copy(e.boundingBox.max),y.probesResolution.value.copy(e.resolution)}WebGLUniforms.upload(eW,e8(b),y,d)}if(a.isShaderMaterial&&!0===a.uniformsNeedUpdate&&(WebGLUniforms.upload(eW,e8(b),y,d),a.uniformsNeedUpdate=!1),a.isSpriteMaterial&&N.setValue(eW,"center",n.center),N.setValue(eW,"modelViewMatrix",n.modelViewMatrix),N.setValue(eW,"normalMatrix",n.normalMatrix),N.setValue(eW,"modelMatrix",n.matrixWorld),void 0!==a.uniformsGroups){let e=a.uniformsGroups;for(let t=0,i=e.length;t<i;t++){let i=e[t];H.update(i,w),H.bind(i,w)}}return w}(e,t,a,n,s);l.setMaterial(n,m);let _=a.index,S=1;if(!0===n.wireframe){if(void 0===(_=v.getWireframeAttribute(a)))return;S=2}let E=a.drawRange,T=a.attributes.position,x=E.start*S,A=(E.start+E.count)*S;null!==f&&(x=Math.max(x,f.start*S),A=Math.min(A,(f.start+f.count)*S)),null!==_?(x=Math.max(x,0),A=Math.min(A,_.count)):null!=T&&(x=Math.max(x,0),A=Math.min(A,T.count));let b=A-x;if(b<0||1/0===b)return;G.setup(s,n,h,a,_);let w=P;if(null!==_&&(p=g.get(_),(w=L).setIndex(p)),s.isMesh)!0===n.wireframe?(l.setLineWidth(n.wireframeLinewidth*eV()),w.setMode(eW.LINES)):w.setMode(eW.TRIANGLES);else if(s.isLine){let e=n.linewidth;void 0===e&&(e=1),l.setLineWidth(e*eV()),s.isLineSegments?w.setMode(eW.LINES):s.isLineLoop?w.setMode(eW.LINE_LOOP):w.setMode(eW.LINE_STRIP)}else s.isPoints?w.setMode(eW.POINTS):s.isSprite&&w.setMode(eW.TRIANGLES);if(s.isBatchedMesh)if(i.get("WEBGL_multi_draw"))w.renderMultiDraw(s._multiDrawStarts,s._multiDrawCounts,s._multiDrawCount);else{let e=s._multiDrawStarts,t=s._multiDrawCounts,i=s._multiDrawCount,r=_?g.get(_).bytesPerElement:1,a=c.get(n).currentProgram.getUniforms();for(let n=0;n<i;n++)a.setValue(eW,"_gl_DrawID",n),w.render(e[n]/r,t[n])}else if(s.isInstancedMesh)w.renderInstances(x,b,s.count);else if(a.isInstancedBufferGeometry){let e=void 0!==a._maxInstanceCount?a._maxInstanceCount:1/0,t=Math.min(a.instanceCount,e);w.renderInstances(x,b,t)}else w.render(x,b)},this.compile=function(e,t){let i=arguments.length>2&&void 0!==arguments[2]?arguments[2]:null;null===i&&(i=e),null!==eu&&eu.renderStart(e,t,i),(en=x.get(i)).init(t),el.push(en),i.traverseVisible(function(e){e.isLight&&e.layers.test(t.layers)&&(en.pushLight(e),e.castShadow&&en.pushShadow(e))}),e!==i&&e.traverseVisible(function(e){e.isLight&&e.layers.test(t.layers)&&(en.pushLight(e),e.castShadow&&en.pushShadow(e))}),en.setupLights(),null!==eu&&eu.updateLights(en.state.lightsArray),ey=this.localClippingEnabled,!0===(eN=R.init(this.clippingPlanes,ey))&&R.setGlobalState(this.clippingPlanes,t),null!==eu&&A.render(en.state.shadowsArray,i,t);let r=new Set;return e.traverse(function(e){if(!(e.isMesh||e.isPoints||e.isLine||e.isSprite))return;let a=e.material;if(a)if(Array.isArray(a))for(let n=0;n<a.length;n++){let o=a[n];eZ(o,i,t,e),r.add(o)}else eZ(a,i,t,e),r.add(a)}),en=el.pop(),null!==eu&&eu.renderEnd(),r},this.compileAsync=function(e,t){let r=arguments.length>2&&void 0!==arguments[2]?arguments[2]:null,a=this.compile(e,t,r);return new Promise(t=>{function r(){(a.forEach(function(e){let t=c.get(e).currentProgram;(void 0===t||t.isReady())&&a.delete(e)}),0===a.size)?t(e):setTimeout(r,10)}null!==i.get("KHR_parallel_shader_compile")?r():setTimeout(r,10)})};let eQ=null;function eJ(){e0.stop()}function e$(){e0.start()}let e0=new a;function e1(e,t,i,r){if(!1===e.visible)return;if(e.layers.test(t.layers)){if(e.isGroup)i=e.renderOrder;else if(e.isLOD)!0===e.autoUpdate&&e.update(t);else if(e.isLightProbeGrid)en.pushLightProbeGrid(e);else if(e.isLight)en.pushLight(e),e.castShadow&&en.pushShadow(e);else if(e.isSprite){if(!e.frustumCulled||e.intersectsFrustum(eI)){r&&eB.setFromMatrixPosition(e.matrixWorld).applyMatrix4(eO);let a=S.update(e),n=e.material;n.visible&&ea.push(e,a,n,i,eB.z,null,t)}}else if((e.isMesh||e.isLine||e.isPoints)&&(!e.frustumCulled||e.intersectsFrustum(eI))){let a=S.update(e),n=e.material;if(r&&(void 0!==e.boundingSphere?(null===e.boundingSphere&&e.computeBoundingSphere(),eB.copy(e.boundingSphere.center)):(null===a.boundingSphere&&a.computeBoundingSphere(),eB.copy(a.boundingSphere.center)),eB.applyMatrix4(e.matrixWorld).applyMatrix4(eO)),Array.isArray(n)){let r=a.groups;for(let o=0,l=r.length;o<l;o++){let l=r[o],s=n[l.materialIndex];s&&s.visible&&ea.push(e,a,s,i,eB.z,l,t)}}else n.visible&&ea.push(e,a,n,i,eB.z,null,t)}}let a=e.children;for(let e=0,n=a.length;e<n;e++)e1(a[e],t,i,r)}function e3(e,t,i,r){let{opaque:a,transmissive:n,transparent:o}=e;en.setupLightsView(i),!0===eN&&R.setGlobalState(ec.clippingPlanes,i),r&&l.viewport(eE.copy(r)),a.length>0&&e4(a,t,i),n.length>0&&e4(n,t,i),o.length>0&&e4(o,t,i),l.buffers.depth.setTest(!0),l.buffers.depth.setMask(!0),l.buffers.color.setMask(!0),l.setPolygonOffset(!1)}function e2(e,t,a,n){if(null!==(!0===a.isScene?a.overrideMaterial:null))return;if(void 0===en.state.transmissionRenderTarget[n.id]){let e=i.has("EXT_color_buffer_half_float")||i.has("EXT_color_buffer_float");en.state.transmissionRenderTarget[n.id]=new r.nWS(1,1,{generateMipmaps:!0,type:e?1016:1009,minFilter:1008,samples:Math.max(4,o.samples),stencilBuffer:k,resolveDepthBuffer:!1,resolveStencilBuffer:!1,storeMultisampledDepthBuffer:!1,storeMultisampledStencilBuffer:!1,colorSpace:r.ppV.workingColorSpace})}let l=en.state.transmissionRenderTarget[n.id],s=n.viewport||eE;l.setSize(s.z*ec.transmissionResolutionScale,s.w*ec.transmissionResolutionScale);let c=ec.getRenderTarget(),u=ec.getActiveCubeFace(),f=ec.getActiveMipmapLevel();ec.setRenderTarget(l),ec.getClearColor(ex),(eR=ec.getClearAlpha())<1&&ec.setClearColor(0xffffff,.5),ec.clear(),eH&&b.render(a);let p=ec.toneMapping;ec.toneMapping=0;let m=n.viewport;if(void 0!==n.viewport&&(n.viewport=void 0),en.setupLightsView(n),!0===eN&&R.setGlobalState(ec.clippingPlanes,n),e4(e,a,n),d.updateMultisampleRenderTarget(l),d.updateRenderTargetMipmap(l),!1===i.has("WEBGL_multisampled_render_to_texture")){let e=!1;for(let i=0,r=t.length;i<r;i++){let{object:r,geometry:o,material:l,group:s}=t[i];if(2===l.side&&r.layers.test(n.layers)){let t=l.side;l.side=1,l.needsUpdate=!0,e5(r,a,n,o,l,s),l.side=t,l.needsUpdate=!0,e=!0}}!0===e&&(d.updateMultisampleRenderTarget(l),d.updateRenderTargetMipmap(l))}ec.setRenderTarget(c,u,f),ec.setClearColor(ex,eR),void 0!==m&&(n.viewport=m),ec.toneMapping=p}function e4(e,t,i){let r=!0===t.isScene?t.overrideMaterial:null;for(let a=0,n=e.length;a<n;a++){let n=e[a],{object:o,geometry:l,group:s}=n,c=n.material;!0===c.allowOverride&&null!==r&&(c=r),o.layers.test(i.layers)&&e5(o,t,i,l,c,s)}}function e5(e,t,i,r,a,n){null!==eu&&a.isNodeMaterial&&eu.setObject(e,a),e.onBeforeRender(ec,t,i,r,a,n),e.modelViewMatrix.multiplyMatrices(i.matrixWorldInverse,e.matrixWorld),e.normalMatrix.getNormalMatrix(e.modelViewMatrix),a.onBeforeRender(ec,t,i,r,e,n),!0===a.transparent&&2===a.side&&!1===a.forceSinglePass?(a.side=1,a.needsUpdate=!0,ec.renderBufferDirect(i,t,r,a,e,n),a.side=0,a.needsUpdate=!0,ec.renderBufferDirect(i,t,r,a,e,n),a.side=2):ec.renderBufferDirect(i,t,r,a,e,n),e.onAfterRender(ec,t,i,r,a,n)}function e6(e,t,i){var r;!0!==t.isScene&&(t=eG);let a=c.get(e),n=en.state.lights,o=en.state.shadowsArray,l=n.state.version,s=E.getParameters(e,n.state,o,t,i,en.state.lightProbeGridArray),d=E.getProgramCacheKey(s),f=a.programs;a.environment=e.isMeshStandardMaterial||e.isMeshLambertMaterial||e.isMeshPhongMaterial?t.environment:null,a.fog=t.fog;let p=e.isMeshStandardMaterial||e.isMeshLambertMaterial&&!e.envMap||e.isMeshPhongMaterial&&!e.envMap;a.envMap=u.get(e.envMap||a.environment,p),a.envMapRotation=null!==a.environment&&null===e.envMap?t.environmentRotation:e.envMapRotation,void 0===f&&(e.addEventListener("dispose",ej),a.programs=f=new Map);let m=f.get(d);if(void 0!==m){if(a.currentProgram===m&&a.lightsStateVersion===l)return te(e,s),m}else s.uniforms=E.getUniforms(e),null!==eu&&e.isNodeMaterial&&eu.build(e,i,s),e.onBeforeCompile(s,ec),m=E.acquireProgram(s,d),f.set(d,m),a.uniforms=s.uniforms;let h=a.uniforms;return(e.isShaderMaterial||e.isRawShaderMaterial)&&!0!==e.clipping||(h.clippingPlanes=R.uniform),te(e,s),a.needsLights=(r=e).isMeshLambertMaterial||r.isMeshToonMaterial||r.isMeshPhongMaterial||r.isMeshStandardMaterial||r.isShadowMaterial||r.isShaderMaterial&&!0===r.lights,a.lightsStateVersion=l,a.needsLights&&(h.ambientLightColor.value=n.state.ambient,h.lightProbe.value=n.state.probe,h.sunLights.value=n.state.sun,h.sunLightShadows.value=n.state.sunShadow,h.directionalLights.value=n.state.directional,h.directionalLightShadows.value=n.state.directionalShadow,h.spotLights.value=n.state.spot,h.spotLightShadows.value=n.state.spotShadow,h.rectAreaLights.value=n.state.rectArea,h.ltc_1.value=n.state.rectAreaLTC1,h.ltc_2.value=n.state.rectAreaLTC2,h.pointLights.value=n.state.point,h.pointLightShadows.value=n.state.pointShadow,h.hemisphereLights.value=n.state.hemi,h.sunShadowMatrix.value=n.state.sunShadowMatrix,h.sunShadowCascade.value=n.state.sunShadowCascade,h.directionalShadowMatrix.value=n.state.directionalShadowMatrix,h.spotLightMatrix.value=n.state.spotLightMatrix,h.spotLightMap.value=n.state.spotLightMap,h.pointShadowMatrix.value=n.state.pointShadowMatrix),a.lightProbeGrid=en.state.lightProbeGridArray.length>0,a.currentProgram=m,a.uniformsList=null,m}function e8(e){if(null===e.uniformsList){let t=e.currentProgram.getUniforms();e.uniformsList=WebGLUniforms.seqWithValue(t.seq,e.uniforms)}return e.uniformsList}function te(e,t){let i=c.get(e);i.outputColorSpace=t.outputColorSpace,i.batching=t.batching,i.batchingColor=t.batchingColor,i.instancing=t.instancing,i.instancingColor=t.instancingColor,i.instancingMorph=t.instancingMorph,i.skinning=t.skinning,i.morphTargets=t.morphTargets,i.morphNormals=t.morphNormals,i.morphColors=t.morphColors,i.morphTargetsCount=t.morphTargetsCount,i.numClippingPlanes=t.numClippingPlanes,i.numIntersection=t.numClipIntersection,i.vertexAlphas=t.vertexAlphas,i.vertexTangents=t.vertexTangents,i.toneMapping=t.toneMapping}function tt(e){let t=c.get(e);return(t.__readFormat!==e.format||t.__readType!==e.type)&&(t.__readFormat=e.format,t.__readType=e.type,t.__formatReadable=o.textureFormatReadable(e.format),t.__typeReadable=o.textureTypeReadable(e.type)),t}e0.setAnimationLoop(function(e){eQ&&eQ(e)}),"u">typeof self&&e0.setContext(self),this.setAnimationLoop=function(e){eQ=e,eX.setAnimationLoop(e),null===e?e0.stop():e0.start()},eX.addEventListener("sessionstart",eJ),eX.addEventListener("sessionend",e$),this.render=function(e,t){if(void 0!==t&&!0!==t.isCamera)return void(0,r.z3S)("WebGLRenderer.render: camera is not an instance of THREE.Camera.");if(!0===ed)return;null!==eu&&eu.renderStart(e,t);let i=!0===eX.enabled&&!0===eX.isPresenting,a=null!==es&&(null===eg||i)&&es.begin(ec,eg);if(!0===e.matrixWorldAutoUpdate&&e.updateMatrixWorld(),null===t.parent&&!0===t.matrixWorldAutoUpdate&&t.updateMatrixWorld(),!0===eX.enabled&&!0===eX.isPresenting&&(null===es||!1===es.isCompositing())&&(!0===eX.cameraAutoUpdate&&eX.updateCamera(t),t=eX.getCamera()),!0===e.isScene&&e.onBeforeRender(ec,e,t,eg),(en=x.get(e,el.length)).init(t),en.state.textureUnits=d.getTextureUnits(),el.push(en),eO.multiplyMatrices(t.projectionMatrix,t.matrixWorldInverse),eI.setFromProjectionMatrix(eO,2e3,t.reversedDepth),ey=this.localClippingEnabled,eN=R.init(this.clippingPlanes,ey),(ea=T.get(e,eo.length)).init(),eo.push(ea),!0===eX.enabled&&!0===eX.isPresenting){let e=ec.xr.getDepthSensingMesh();null!==e&&e1(e,t,-1/0,ec.sortObjects)}e1(e,t,0,ec.sortObjects),ea.finish(),null!==eu&&eu.updateLights(en.state.lightsArray),!0===ec.sortObjects&&ea.sort(eP,eL),(eH=!1===eX.enabled||!1===eX.isPresenting||!1===eX.hasDepthSensing())&&b.addToRenderList(ea,e),this.info.render.frame++,!0===this.info.autoReset&&this.info.reset(),!0===eN&&R.beginShadows();let n=en.state.shadowsArray;if(A.render(n,e,t),!0===eN&&R.endShadows(),!1===(a&&es.hasRenderPass())){let i=ea.opaque,r=ea.transmissive;if(en.setupLights(),t.isArrayCamera){let a=t.cameras;if(r.length>0)for(let t=0,n=a.length;t<n;t++)e2(i,r,e,a[t]);eH&&b.render(e);for(let t=0,i=a.length;t<i;t++){let i=a[t];e3(ea,e,i,i.viewport)}}else r.length>0&&e2(i,r,e,t),eH&&b.render(e),e3(ea,e,t)}null!==eg&&0===e_&&(d.updateMultisampleRenderTarget(eg),d.updateRenderTargetMipmap(eg)),a&&es.end(ec),!0===e.isScene&&e.onAfterRender(ec,e,t),G.resetDefaultState(),ev=-1,eS=null,el.pop(),el.length>0?(en=el[el.length-1],d.setTextureUnits(en.state.textureUnits),!0===eN&&R.setGlobalState(ec.clippingPlanes,en.state.camera)):en=null,eo.pop(),ea=eo.length>0?eo[eo.length-1]:null,null!==eu&&eu.renderEnd()},this.getActiveCubeFace=function(){return eh},this.getActiveMipmapLevel=function(){return e_},this.getRenderTarget=function(){return eg},this.setRenderTargetTextures=function(e,t,i){let r=c.get(e);r.__autoAllocateDepthBuffer=!1===e.resolveDepthBuffer,!1===r.__autoAllocateDepthBuffer&&(r.__useRenderToTexture=!1),c.get(e.texture).__webglTexture=t,c.get(e.depthTexture).__webglTexture=r.__autoAllocateDepthBuffer?void 0:i,r.__hasExternalTextures=!0},this.setRenderTargetFramebuffer=function(e,t){let i=c.get(e);i.__webglFramebuffer=t,i.__useDefaultFramebuffer=void 0===t},this.setRenderTarget=function(e){let t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:0,i=arguments.length>2&&void 0!==arguments[2]?arguments[2]:0;eg=e,eh=t,e_=i;let r=null,a=!1,n=!1;if(e){let o=c.get(e);if(void 0!==o.__useDefaultFramebuffer){l.bindFramebuffer(eW.FRAMEBUFFER,o.__webglFramebuffer),eE.copy(e.viewport),eM.copy(e.scissor),eT=e.scissorTest,l.viewport(eE),l.scissor(eM),l.setScissorTest(eT),ev=-1;return}if(void 0===o.__webglFramebuffer)d.setupRenderTarget(e);else if(o.__hasExternalTextures)d.rebindTextures(e,c.get(e.texture).__webglTexture,c.get(e.depthTexture).__webglTexture);else if(e.depthBuffer){let t=e.depthTexture;if(o.__boundDepthTexture!==t){if(null!==t&&c.has(t)&&(e.width!==t.image.width||e.height!==t.image.height))throw Error("THREE.WebGLRenderer: Attached DepthTexture is initialized to the incorrect size.");d.setupDepthRenderbuffer(e)}}let s=e.texture;(s.isData3DTexture||s.isDataArrayTexture||s.isCompressedArrayTexture)&&(n=!0);let u=c.get(e).__webglFramebuffer;e.isWebGLCubeRenderTarget?(r=Array.isArray(u[t])?u[t][i]:u[t],a=!0):r=e.samples>0&&!1===d.useMultisampledRTT(e)?c.get(e).__webglMultisampledFramebuffer:Array.isArray(u)?u[i]:u,eE.copy(e.viewport),eM.copy(e.scissor),eT=e.scissorTest}else eE.copy(ew).multiplyScalar(eC).floor(),eM.copy(eU).multiplyScalar(eC).floor(),eT=eD;if(0!==i&&(r=ef),l.bindFramebuffer(eW.FRAMEBUFFER,r)&&l.drawBuffers(e,r),l.viewport(eE),l.scissor(eM),l.setScissorTest(eT),a){let r=c.get(e.texture);eW.framebufferTexture2D(eW.FRAMEBUFFER,eW.COLOR_ATTACHMENT0,eW.TEXTURE_CUBE_MAP_POSITIVE_X+t,r.__webglTexture,i)}else if(n)for(let r=0;r<e.textures.length;r++){let a=c.get(e.textures[r]);eW.framebufferTextureLayer(eW.FRAMEBUFFER,eW.COLOR_ATTACHMENT0+r,a.__webglTexture,i,t)}else if(null!==e&&0!==i){let t=c.get(e.texture);eW.framebufferTexture2D(eW.FRAMEBUFFER,eW.COLOR_ATTACHMENT0,eW.TEXTURE_2D,t.__webglTexture,i)}ev=-1},this.readRenderTargetPixels=function(e,t,i,a,n,o,s){let d=arguments.length>7&&void 0!==arguments[7]?arguments[7]:0;if(!(e&&e.isWebGLRenderTarget))return void(0,r.z3S)("WebGLRenderer.readRenderTargetPixels: renderTarget is not THREE.WebGLRenderTarget.");let u=c.get(e).__webglFramebuffer;if(e.isWebGLCubeRenderTarget&&void 0!==s&&(u=u[s]),u){l.bindFramebuffer(eW.FRAMEBUFFER,u);try{let l=e.textures[d],s=l.format,c=l.type;e.textures.length>1&&eW.readBuffer(eW.COLOR_ATTACHMENT0+d);let u=tt(l);if(!1===u.__formatReadable)return void(0,r.z3S)("WebGLRenderer.readRenderTargetPixels: renderTarget is not in RGBA or implementation defined format.");if(!1===u.__typeReadable)return void(0,r.z3S)("WebGLRenderer.readRenderTargetPixels: renderTarget is not in UnsignedByteType or implementation defined type.");t>=0&&t<=e.width-a&&i>=0&&i<=e.height-n&&eW.readPixels(t,i,a,n,F.convert(s),F.convert(c),o)}finally{let e=null!==eg?c.get(eg).__webglFramebuffer:null;l.bindFramebuffer(eW.FRAMEBUFFER,e)}}},this.readRenderTargetPixelsAsync=async function(e,t,i,a,n,o,s){let d=arguments.length>7&&void 0!==arguments[7]?arguments[7]:0;if(!(e&&e.isWebGLRenderTarget))throw Error("THREE.WebGLRenderer.readRenderTargetPixels: renderTarget is not THREE.WebGLRenderTarget.");let u=c.get(e).__webglFramebuffer;if(e.isWebGLCubeRenderTarget&&void 0!==s&&(u=u[s]),u)if(t>=0&&t<=e.width-a&&i>=0&&i<=e.height-n){l.bindFramebuffer(eW.FRAMEBUFFER,u);let s=e.textures[d],f=s.format,p=s.type;e.textures.length>1&&eW.readBuffer(eW.COLOR_ATTACHMENT0+d);let m=tt(s);if(!1===m.__formatReadable)throw Error("THREE.WebGLRenderer.readRenderTargetPixelsAsync: renderTarget is not in RGBA or implementation defined format.");if(!1===m.__typeReadable)throw Error("THREE.WebGLRenderer.readRenderTargetPixelsAsync: renderTarget is not in UnsignedByteType or implementation defined type.");let h=eW.createBuffer();eW.bindBuffer(eW.PIXEL_PACK_BUFFER,h),eW.bufferData(eW.PIXEL_PACK_BUFFER,o.byteLength,eW.STREAM_READ),eW.readPixels(t,i,a,n,F.convert(f),F.convert(p),0),eW.bindBuffer(eW.PIXEL_PACK_BUFFER,null);let _=null!==eg?c.get(eg).__webglFramebuffer:null;l.bindFramebuffer(eW.FRAMEBUFFER,_);let g=eW.fenceSync(eW.SYNC_GPU_COMMANDS_COMPLETE,0);return eW.flush(),await (0,r.jej)(eW,g,4),eW.bindBuffer(eW.PIXEL_PACK_BUFFER,h),eW.getBufferSubData(eW.PIXEL_PACK_BUFFER,0,o),eW.bindBuffer(eW.PIXEL_PACK_BUFFER,null),eW.deleteBuffer(h),eW.deleteSync(g),o}else throw Error("THREE.WebGLRenderer.readRenderTargetPixelsAsync: requested read bounds are out of range.")},this.copyFramebufferToTexture=function(e){let t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:null,i=arguments.length>2&&void 0!==arguments[2]?arguments[2]:0,r=Math.pow(2,-i),a=Math.floor(e.image.width*r),n=Math.floor(e.image.height*r),o=null!==t?t.x:0,s=null!==t?t.y:0;d.setTexture2D(e,0),eW.copyTexSubImage2D(eW.TEXTURE_2D,i,0,0,o,s,a,n),l.unbindTexture()},this.copyTextureToTexture=function(e,t){let i,r,a,n,o,s,u,f,p,m,h=arguments.length>2&&void 0!==arguments[2]?arguments[2]:null,_=arguments.length>3&&void 0!==arguments[3]?arguments[3]:null,g=arguments.length>4&&void 0!==arguments[4]?arguments[4]:0,v=arguments.length>5&&void 0!==arguments[5]?arguments[5]:0,S=e.isCompressedTexture?e.mipmaps[v]:e.image;if(null!==h)i=h.max.x-h.min.x,r=h.max.y-h.min.y,a=h.isBox3?h.max.z-h.min.z:1,n=h.min.x,o=h.min.y,s=h.isBox3?h.min.z:0;else{let t=Math.pow(2,-g);i=Math.floor(S.width*t),r=Math.floor(S.height*t),a=e.isDataArrayTexture?S.depth:e.isData3DTexture?Math.floor(S.depth*t):1,n=0,o=0,s=0}null!==_?(u=_.x,f=_.y,p=_.z):(u=0,f=0,p=0);let E=F.convert(t.format),M=F.convert(t.type);t.isData3DTexture?(d.setTexture3D(t,0),m=eW.TEXTURE_3D):t.isDataArrayTexture||t.isCompressedArrayTexture?(d.setTexture2DArray(t,0),m=eW.TEXTURE_2D_ARRAY):(d.setTexture2D(t,0),m=eW.TEXTURE_2D),l.activeTexture(eW.TEXTURE0),l.pixelStorei(eW.UNPACK_FLIP_Y_WEBGL,t.flipY),l.pixelStorei(eW.UNPACK_PREMULTIPLY_ALPHA_WEBGL,t.premultiplyAlpha),l.pixelStorei(eW.UNPACK_ALIGNMENT,t.unpackAlignment);let T=l.getParameter(eW.UNPACK_ROW_LENGTH),x=l.getParameter(eW.UNPACK_IMAGE_HEIGHT),R=l.getParameter(eW.UNPACK_SKIP_PIXELS),A=l.getParameter(eW.UNPACK_SKIP_ROWS),b=l.getParameter(eW.UNPACK_SKIP_IMAGES);l.pixelStorei(eW.UNPACK_ROW_LENGTH,S.width),l.pixelStorei(eW.UNPACK_IMAGE_HEIGHT,S.height),l.pixelStorei(eW.UNPACK_SKIP_PIXELS,n),l.pixelStorei(eW.UNPACK_SKIP_ROWS,o),l.pixelStorei(eW.UNPACK_SKIP_IMAGES,s);let C=e.isDataArrayTexture||e.isData3DTexture,P=t.isDataArrayTexture||t.isData3DTexture;if(e.isDepthTexture){let d=c.get(e),m=c.get(t),h=c.get(d.__renderTarget),_=c.get(m.__renderTarget);l.bindFramebuffer(eW.READ_FRAMEBUFFER,h.__webglFramebuffer),l.bindFramebuffer(eW.DRAW_FRAMEBUFFER,_.__webglFramebuffer);for(let l=0;l<a;l++)C&&(eW.framebufferTextureLayer(eW.READ_FRAMEBUFFER,eW.COLOR_ATTACHMENT0,c.get(e).__webglTexture,g,s+l),eW.framebufferTextureLayer(eW.DRAW_FRAMEBUFFER,eW.COLOR_ATTACHMENT0,c.get(t).__webglTexture,v,p+l)),eW.blitFramebuffer(n,o,i,r,u,f,i,r,eW.DEPTH_BUFFER_BIT,eW.NEAREST);l.bindFramebuffer(eW.READ_FRAMEBUFFER,null),l.bindFramebuffer(eW.DRAW_FRAMEBUFFER,null)}else if(0!==g||e.isRenderTargetTexture||c.has(e)){let d=c.get(e),h=c.get(t);l.bindFramebuffer(eW.READ_FRAMEBUFFER,ep),l.bindFramebuffer(eW.DRAW_FRAMEBUFFER,em);for(let e=0;e<a;e++)C?eW.framebufferTextureLayer(eW.READ_FRAMEBUFFER,eW.COLOR_ATTACHMENT0,d.__webglTexture,g,s+e):eW.framebufferTexture2D(eW.READ_FRAMEBUFFER,eW.COLOR_ATTACHMENT0,eW.TEXTURE_2D,d.__webglTexture,g),P?eW.framebufferTextureLayer(eW.DRAW_FRAMEBUFFER,eW.COLOR_ATTACHMENT0,h.__webglTexture,v,p+e):eW.framebufferTexture2D(eW.DRAW_FRAMEBUFFER,eW.COLOR_ATTACHMENT0,eW.TEXTURE_2D,h.__webglTexture,v),0!==g?eW.blitFramebuffer(n,o,i,r,u,f,i,r,eW.COLOR_BUFFER_BIT,eW.NEAREST):P?eW.copyTexSubImage3D(m,v,u,f,p+e,n,o,i,r):eW.copyTexSubImage2D(m,v,u,f,n,o,i,r);l.bindFramebuffer(eW.READ_FRAMEBUFFER,null),l.bindFramebuffer(eW.DRAW_FRAMEBUFFER,null)}else P?e.isDataTexture||e.isData3DTexture?eW.texSubImage3D(m,v,u,f,p,i,r,a,E,M,S.data):t.isCompressedArrayTexture?eW.compressedTexSubImage3D(m,v,u,f,p,i,r,a,E,S.data):eW.texSubImage3D(m,v,u,f,p,i,r,a,E,M,S):e.isDataTexture?eW.texSubImage2D(eW.TEXTURE_2D,v,u,f,i,r,E,M,S.data):e.isCompressedTexture?eW.compressedTexSubImage2D(eW.TEXTURE_2D,v,u,f,S.width,S.height,E,S.data):eW.texSubImage2D(eW.TEXTURE_2D,v,u,f,i,r,E,M,S);l.pixelStorei(eW.UNPACK_ROW_LENGTH,T),l.pixelStorei(eW.UNPACK_IMAGE_HEIGHT,x),l.pixelStorei(eW.UNPACK_SKIP_PIXELS,R),l.pixelStorei(eW.UNPACK_SKIP_ROWS,A),l.pixelStorei(eW.UNPACK_SKIP_IMAGES,b),0===v&&t.generateMipmaps&&eW.generateMipmap(m),l.unbindTexture()},this.initRenderTarget=function(e){void 0===c.get(e).__webglFramebuffer&&d.setupRenderTarget(e)},this.initTexture=function(e){e.isCubeTexture?d.setTextureCube(e,0):e.isData3DTexture?d.setTexture3D(e,0):e.isDataArrayTexture||e.isCompressedArrayTexture?d.setTexture2DArray(e,0):d.setTexture2D(e,0),l.unbindTexture()},this.resetState=function(){eh=0,e_=0,eg=null,l.reset(),G.reset()},"u">typeof __THREE_DEVTOOLS__&&__THREE_DEVTOOLS__.dispatchEvent(new CustomEvent("observe",{detail:this}))}}}}]);