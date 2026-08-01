plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "com.gobelino.agent"
    compileSdk = 34

    signingConfigs {
        getByName("debug") {
            // Se le variabili d'ambiente sono presenti (CI) usa il keystore fisso decodificato dal secret.
            // In locale, se non sono settate, Gradle ricade sul debug.keystore di default (~/.android/debug.keystore).
            val ciKeystorePath = System.getenv("DEBUG_KEYSTORE_PATH")
            if (ciKeystorePath != null) {
                storeFile = file(ciKeystorePath)
                storePassword = System.getenv("DEBUG_KEYSTORE_PASSWORD")
                keyAlias = System.getenv("DEBUG_KEY_ALIAS")
                keyPassword = System.getenv("DEBUG_KEY_PASSWORD")
            }
        }
    }

    defaultConfig {
        applicationId = "com.gobelino.agent"
        minSdk = 26 // Device Owner provisioning via QR requires API 24+; 26 keeps WorkManager simple
        targetSdk = 34
        // In CI passiamo APP_VERSION_CODE=github.run_number cosi' ogni build ha un versionCode crescente
        // e Android accetta l'update sui dispositivi gia' arruolati.
        versionCode = System.getenv("APP_VERSION_CODE")?.toIntOrNull() ?: 1
        versionName = "1.0.0"
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    
    buildFeatures {
        buildConfig = true
    }

    kotlinOptions {
        jvmTarget = "17"
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.work:work-runtime-ktx:2.9.1")
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.8.4")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.8.1")
    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("org.json:json:20240303")
    implementation("com.journeyapps:zxing-android-embedded:4.3.0")
}
