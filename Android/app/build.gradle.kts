import groovy.json.JsonSlurper
import groovy.json.JsonOutput

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.plugin.compose")
}
val firebaseFile = file("google-services.json")
val firebaseConfig = if (firebaseFile.exists()) JsonSlurper().parse(firebaseFile) as Map<*, *> else emptyMap<Any,Any>()
val firebaseProject = firebaseConfig["project_info"] as? Map<*, *> ?: emptyMap<Any,Any>()
val firebaseClient = (firebaseConfig["client"] as? List<*>)?.filterIsInstance<Map<*, *>>()?.firstOrNull {
    val info = it["client_info"] as? Map<*, *>
    (info?.get("android_client_info") as? Map<*, *>)?.get("package_name") == "com.edwinortizp.lifetracker"
} ?: emptyMap<Any,Any>()
val firebaseInfo = firebaseClient["client_info"] as? Map<*, *> ?: emptyMap<Any,Any>()
val firebaseApiKey = ((firebaseClient["api_key"] as? List<*>)?.firstOrNull() as? Map<*, *>)?.get("current_key")?.toString().orEmpty()
val firebaseAppId = firebaseInfo["mobilesdk_app_id"]?.toString().orEmpty()
android {
    namespace = "com.edwinortizp.lifetracker"
    compileSdk = 36
    defaultConfig {
        applicationId = "com.edwinortizp.lifetracker"
        minSdk = 26
        targetSdk = 36
        versionCode = 2
        versionName = "0.2.0"
        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
        buildConfigField("String", "MISO_BASE_URL", "\"https://miso.novapixel.org/\"")
        buildConfigField("boolean", "FIREBASE_CONFIGURED", (firebaseAppId.isNotEmpty() && firebaseApiKey.isNotEmpty() && !firebaseProject["project_id"]?.toString().isNullOrEmpty() && !firebaseProject["project_number"]?.toString().isNullOrEmpty()).toString())
        buildConfigField("String", "FIREBASE_APP_ID", JsonOutput.toJson(firebaseAppId))
        buildConfigField("String", "FIREBASE_API_KEY", JsonOutput.toJson(firebaseApiKey))
        buildConfigField("String", "FIREBASE_SENDER_ID", JsonOutput.toJson(firebaseProject["project_number"]?.toString().orEmpty()))
        buildConfigField("String", "FIREBASE_PROJECT_ID", JsonOutput.toJson(firebaseProject["project_id"]?.toString().orEmpty()))
    }
    buildTypes { release { isMinifyEnabled = false } }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    buildFeatures { compose = true; buildConfig = true }
}
kotlin { compilerOptions { jvmTarget.set(org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17) } }
dependencies {
    implementation("androidx.webkit:webkit:1.14.0")
    implementation("com.google.firebase:firebase-messaging:25.0.1")
    implementation("androidx.fragment:fragment-ktx:1.8.9")
    implementation("androidx.core:core-ktx:1.17.0")
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.10.0")
    implementation("androidx.lifecycle:lifecycle-viewmodel-ktx:2.10.0")
    implementation("androidx.activity:activity-compose:1.12.2")
    implementation(platform("androidx.compose:compose-bom:2026.01.00"))
    implementation("androidx.compose.ui:ui")
    implementation("androidx.compose.material3:material3")
    testImplementation("junit:junit:4.13.2")
    androidTestImplementation("androidx.test.ext:junit:1.3.0")
    androidTestImplementation("androidx.test:runner:1.7.0")
    androidTestImplementation("androidx.test:rules:1.7.0")
}
